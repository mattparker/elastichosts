<?php
/**
 * User: matt
 * Date: 23/06/14
 * Time: 10:12
 */


/**
 * Class EHBuilder
 *
 * Runs command line arguments generated by EHServerBuilder and EHDriveBuilder
 *
 */

class EHBuilder {



    /**
     * @var EHServerBuilder
     */
    private $serverBuilder;
    /**
     * @var EHDriveBuilder
     */
    private $driveBuilder;

    /**
     * @var Runner
     */
    private $runner;

    /**
     * @var EHServer[]
     */
    private $serversCreated = [];

    /**
     * @var array
     */
    private $pollingQueue = [];

    /**
     * Number of seconds to wait when polling for drive imaging to complete
     * $var int
     */
    const SLEEP_TIMEOUT = 5;

    /**
     * @var EHLogger
     */
    private $logger;


    /**
     * @param EHServerBuilder $serverBuilder
     * @param EHDriveBuilder  $driveBuilder
     * @param Runner          $runner
     */
    public function __construct (EHServerBuilder $serverBuilder, EHDriveBuilder $driveBuilder, Runner $runner) {
        $this->serverBuilder = $serverBuilder;
        $this->driveBuilder = $driveBuilder;
        $this->runner = $runner;
        // default logger - mostly don't need to inject another one
        $this->logger = new EHLogger();
    }



    /**
     * @param EHServer $server
     */
    public function build (EHServer $server) {

        $avoidSharingHardware = $server->getConfigValue('avoid');
        $drive_ids_to_avoid = null;
        $server_ids_to_avoid = null;
        if ($avoidSharingHardware !== null) {
            list($server_ids_to_avoid, $drive_ids_to_avoid) = $this->getServerAndDriveIds($avoidSharingHardware);
        }

        // make drives
        $drives = $server->getDrives();
        $this->buildDrives($drives, $drive_ids_to_avoid);

        $this->waitForDriveImage();

        // now drives should all have their UUIDs
        $this->buildServer($server, $server_ids_to_avoid, $drive_ids_to_avoid);

        $this->serversCreated[$server->getName()] = $server;

    }


    /**
     * Given a list of (user-supplied) server names, find their (ElasticHosts guid) server and drive IDs
     *
     * @param array $serverNames First item is array of server guids, Second is array of drive guids
     *
     * @return array
     */
    private function getServerAndDriveIds (array $serverNames = array()) {

        $server_ids = [];
        $drive_ids = [];

        foreach ($serverNames as $serverName) {
            if (!array_key_exists($serverName, $this->serversCreated)) {
                continue;
            }
            $serverToAvoid = $this->serversCreated[$serverName];
            $server_ids[] = $serverToAvoid->getIdentifier();
            $drive_ids += $serverToAvoid->getDriveIdentifiers();
        }
        return [$server_ids, $drive_ids];
    }

    /**
     * @param string $command
     * @param array $args
     *
     * @return mixed
     */
    private function run ($command, array $args = array()) {
        $this->log("  [running $command]");
        return $this->runner->run($command, $args);
    }



    /**
     * Mainly for testing so we can now show the normal CLI progress text
     * @param $logger
     */
    public function setLogger ($logger) {
        $this->logger = $logger;
    }

    /**
     * @param $message
     */
    protected function log ($message) {
        $this->logger->log($message);
    }


    /**
     * Creates a server with the drives just created
     *
     * @param EHServer $server
     * @param array    $serverIdsToAvoid
     * @param array    $driveIdsToAvoid
     */
    private function buildServer (EHServer $server, array $serverIdsToAvoid = null, array $driveIdsToAvoid = null) {

        if ($serverIdsToAvoid) {
            $server->avoidSharingHardwareWithServers($serverIdsToAvoid);
        }
        if ($driveIdsToAvoid) {
            $server->avoidSharingHardwareWithDrives($driveIdsToAvoid);
        }


        list($command, $args) = $this->serverBuilder->create($server);

        $this->log("Creating server " . $server->getConfigValue('name'));
        $info = $this->run($command, $args);
        $this->serverBuilder->parseResponse($server, $info, EHServerBuilder::CREATE);

    }



    /**
     * @param array $drives
     * @param array $driveIdsToAvoid Don't want to share hardware with...
     */
    private function buildDrives (array $drives, array $driveIdsToAvoid = null) {

        foreach ($drives as $drive) {
            /** @var EHDrive $drive */

            list($command, $args) = $this->driveBuilder->create($drive, $driveIdsToAvoid);

            $this->log("Creating drive " . $drive->getName());

            $info = $this->run($command, $args);
            $this->driveBuilder->parseResponse($drive, $info, EHDriveBuilder::CREATE);


            $this->createImageOnDrive($drive);

        }
    }



    /**
     *
     * @param EHDrive $drive
     *
     */
    private function createImageOnDrive (EHDrive $drive) {
        $image = $drive->getImage();

        if ($image && constant('EHDriveBuilder::' . $drive->getImage())) {

            $this->log("Creating image on drive " . $image);

            list($imageCommand, $args) = $this->driveBuilder->image($drive, constant('EHDriveBuilder::' . $drive->getImage()));
            $this->run($imageCommand, $args);

            $this->pollForImagingComplete($drive);
        }
    }




    /**
     * Adds a drive to the queue for drives to check if they've finsihed imaging
     * @param EHDrive $drive
     */
    private function pollForImagingComplete (EHDrive $drive) {
        $this->pollingQueue[] = $drive;
    }




    /**
     * Poll the API repeatedly until the drive imaging is complete
     */
    private function waitForDriveImage () {

        $queue = $this->pollingQueue;

        while (count($queue) > 0) {

            $this->log("Waiting for drive images to complete...");

            for ($driveNum = count($queue) - 1; $driveNum >= 0; $driveNum--) {

                $drive = $queue[$driveNum];

                // The call to info contains imaging progress
                list($command, $args) = $this->driveBuilder->info($drive);
                $info = $this->run($command, $args);

                $stillWaiting = $this->driveBuilder->parseResponse($drive, $info, EHDriveBuilder::IS_IMAGING_COMPLETE);
                if ($stillWaiting === 'false') {
                    unset($queue[$driveNum]);
                }

            }

            // if it's still got some in
            if (count($queue) > 0) {
                sleep(self::SLEEP_TIMEOUT);
            }

        }

        $this->pollingQueue = [];
    }

} 