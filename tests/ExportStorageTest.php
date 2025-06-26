<?php
declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

if (!defined('PIMCORE_SYSTEM_TEMP_DIRECTORY')) {
    define('PIMCORE_SYSTEM_TEMP_DIRECTORY', sys_get_temp_dir());
}

use Doctrine\Persistence\ConnectionRegistry;
use Instride\Bundle\DataDefinitionsBundle\Provider\JsonProvider;
use Instride\Bundle\DataDefinitionsBundle\Service\StorageLocator;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Pimcore\Helper\LongRunningHelper;
use Psr\Container\ContainerInterface;
use Instride\Bundle\DataDefinitionsBundle\Model\ExportDefinitionInterface;

class ExportStorageTest extends TestCase
{
    public function testExportToFlysystemStorage(): void
    {
        $dir = sys_get_temp_dir().'/dd_test_'.uniqid();
        mkdir($dir);
        $adapter = new LocalFilesystemAdapter($dir);
        $fs = new Filesystem($adapter);

        $container = new class($fs) implements ContainerInterface {
            public function __construct(private FilesystemOperator $fs) {}
            public function get(string $id)
            {
                return $this->fs;
            }
            public function has(string $id): bool
            {
                return true;
            }
        };

        $locator = new StorageLocator($container);

        $registry = new class implements ConnectionRegistry {
            public function getDefaultConnectionName(){}
            public function getConnection(?string $name = null){}
            public function getConnections(){ return []; }
            public function getConnectionNames(){ return []; }
        };

        $helper = new LongRunningHelper($registry);

        $provider = new JsonProvider($locator, $helper);
        $definition = $this->createMock(ExportDefinitionInterface::class);
        $provider->addExportData(['foo' => 'bar'], [], $definition, []);
        $provider->exportData([], $definition, ['storage' => 's3', 'file' => 'out.json']);

        $this->assertTrue($fs->fileExists('out.json'));
        $this->assertSame('[{"foo":"bar"}]', $fs->read('out.json'));
    }
}
