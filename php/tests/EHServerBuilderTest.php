<?php
/**
 * User: matt
 * Date: 23/06/14
 * Time: 21:40
 */
require_once 'phpunit-bootstrap.php';

class EHServerBuilderTest extends PHPUnit_Framework_TestCase {



    public function test_instance () {
        $builder = new EHServerBuilder();
    }


    public function test_a_server_without_a_drive_throws_exception () {
        $cfg = [
            'name' => 'testserver1',
            'cpu' => '500',
            'mem' => '256',
            'nic:0:model' => 'e1000',
            'nic:0:dhcp' => 'auto',
            'boot' => 'ide:0:0'
        ];
        $server = new EHServer((object)$cfg);
        $builder = new EHServerBuilder();

        $this->setExpectedException('LogicException');
        $builder->create($server);
    }

    public function test_a_simple_server () {

        $driveCfg = ['name' => 'testdrive', 'size' => 1000000];

        $cfg = [
            'name' => 'testserver1',
            'cpu' => '500',
            'mem' => '256',
            'nic:0:model' => 'e1000',
            'nic:0:dhcp' => 'auto',
            'boot' => 'ide:0:0',
            'drives' => [$driveCfg]
        ];
        $server = new EHServer((object)$cfg);
        // set the id on the drive as if it'd been created
        $drives = $server->getDrives();
        $drives[0]->setIdentifier('abc123');

        $builder = new EHServerBuilder();

        $output = $builder->create($server);

        $this->assertContains('servers create', $output);
        $this->assertContains('name testserver1', $output);
        $this->assertContains('cpu 500', $output);
        $this->assertContains('mem 256', $output);
        $this->assertContains('nic:0:model e1000', $output);
        $this->assertContains('nic:0:dhcp auto', $output);
        $this->assertContains('boot ide:0:0', $output);
        $this->assertContains('ide:0:0 abc123', $output);
    }

    public function test_a_server_with_lots_of_drives () {
        $driveCfg1 = ['name' => 'testdrive1', 'size' => 1000000];
        $driveCfg2 = ['name' => 'testdrive2', 'size' => 2000000];
        $driveCfg3 = ['name' => 'testdrive3', 'size' => 3000000];
        $driveCfg4 = ['name' => 'testdrive4', 'size' => 4000000];

        $cfg = [
            'name' => 'testserver1',
            'cpu' => '500',
            'mem' => '256',
            'nic:0:model' => 'e1000',
            'nic:0:dhcp' => 'auto',
            'boot' => 'ide:0:0',
            'drives' => [$driveCfg1, $driveCfg2, $driveCfg3, $driveCfg4]
        ];
        $server = new EHServer((object)$cfg);
        // set the id on the drive as if it'd been created
        $i = 1;
        foreach ($server->getDrives() as $drive) {
            $drive->setIdentifier('driveid' . $i);
            $i++;
        }

        $builder = new EHServerBuilder();

        $output = $builder->create($server);
        $this->assertContains('ide:0:0 driveid1', $output);
        $this->assertContains('ide:0:1 driveid2', $output);
        $this->assertContains('ide:1:0 driveid3', $output);
        $this->assertContains('ide:1:1 driveid4', $output);
    }


    public function test_a_server_with_too_many_drives () {
        $driveCfg1 = ['name' => 'testdrive1', 'size' => 1000000];
        $driveCfg2 = ['name' => 'testdrive2', 'size' => 2000000];
        $driveCfg3 = ['name' => 'testdrive3', 'size' => 3000000];
        $driveCfg4 = ['name' => 'testdrive4', 'size' => 4000000];
        $driveCfg5 = ['name' => 'testdrive5', 'size' => 4000000];

        $cfg = [
            'name' => 'testserver1',
            'cpu' => '500',
            'mem' => '256',
            'nic:0:model' => 'e1000',
            'nic:0:dhcp' => 'auto',
            'boot' => 'ide:0:0',
            'drives' => [$driveCfg1, $driveCfg2, $driveCfg3, $driveCfg4, $driveCfg5]
        ];
        $server = new EHServer((object)$cfg);
        // set the id on the drive as if it'd been created
        $i = 1;
        foreach ($server->getDrives() as $drive) {
            $drive->setIdentifier('driveid' . $i);
            $i++;
        }

        $builder = new EHServerBuilder();

        $this->setExpectedException('Exception');
        $builder->create($server);
    }


    public function test_we_can_get_ip_and_id_from_response () {

        $id = '55559c30-1f11-4363-ac54-dsd98sd98sd';
        $ip = '91.203.56.132';
        $response = [
            'boot ide:0:0',
            'cpu 500',
            'ide:0:0 6052916e-102f-4db7-abdd-fd98f0d9f8d',
            'ide:0:0:read:bytes 0',
            'ide:0:0:read:requests 0',
            'ide:0:0:write:bytes 0',
            'ide:0:0:write:requests 0',
            'mem 256',
            'name testserver1',
            'nic:0:dhcp auto',
            'nic:0:dhcp:ip ' . $ip,
            'nic:0:model e1000',
            'server ' . $id,
            'smp:cores 1',
            'started 1403554639',
            'status active',
            'user eeeeeee-1111-1111-ffff-6f6f6f6f6f6'
        ];
        $builder = new EHServerBuilder();
        $cfg = [
            'name' => 'testserver1',
            'cpu' => '500'
        ];
        $server = new EHServer((object)$cfg);

        $builder->parseResponse($server, $response, EHServerBuilder::CREATE);

        $this->assertEquals($ip, $server->getPublicIp());
        $this->assertEquals($id, $server->getIdentifier());

    }
}
 