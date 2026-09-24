<?php

namespace Neopayment\NboInstaller\Tests\Console;

use Neopayment\NboInstaller\Console\NewModuleCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class NewModuleCommandTest extends TestCase
{
    private string $workspace;
    private string $originalDirectory;
    private CommandTester $command;

    protected function setUp(): void
    {
        $this->originalDirectory = getcwd();
        $this->workspace = sys_get_temp_dir().'/nbo-installer-test-'.bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($this->workspace);
        chdir($this->workspace);
        $this->command = new CommandTester(new NewModuleCommand());
    }

    protected function tearDown(): void
    {
        chdir($this->originalDirectory);
        (new Filesystem())->remove($this->workspace);
    }

    public function testGeneratesModuleWithDefaultsAndNormalizedCode(): void
    {
        self::assertSame(Command::SUCCESS, $this->command->execute(['code' => 'CustomerOrders']));
        $target = $this->workspace.'/nbo-customer-orders';
        $composer = $this->readJson($target.'/composer.json');
        $npm = $this->readJson($target.'/package.json');

        self::assertSame('neopayment/nbo-customer-orders', $composer['name']);
        self::assertSame('Customer Orders module for NBO', $composer['description']);
        self::assertSame('src/', $composer['autoload']['psr-4']['NeoPayment\\CustomerOrders\\']);
        self::assertSame('database/seeders/', $composer['autoload']['psr-4']['NeoPayment\\CustomerOrders\\Database\\Seeders\\']);
        self::assertSame(['NeoPayment\\CustomerOrders\\Providers\\NboCustomerOrdersServiceProvider'], $composer['extra']['laravel']['providers']);
        self::assertSame('customer-orders', $composer['extra']['nbo-module']['code']);
        self::assertSame('Customer Orders', $composer['extra']['nbo-module']['name']);
        self::assertSame('NeoPayment\\CustomerOrders\\Database\\Seeders\\CustomerOrdersModuleSeeder', $composer['extra']['nbo-module']['seeder']);
        self::assertSame(['resources/css/customer-orders.css'], $composer['extra']['nbo-module']['vite']['laravel']['input']);
        self::assertSame('@neopayment/nbo-customer-orders', $npm['name']);
        self::assertSame('customer-orders', $npm['nbo']['code']);
        self::assertSame('Customer Orders', $npm['nbo']['name']);

        foreach ([
            'src/Http/Controllers/ModuleController.php' => 'src/Http/Controllers/CustomerOrdersController.php',
            'src/Providers/ModuleServiceProvider.php' => 'src/Providers/NboCustomerOrdersServiceProvider.php',
            'database/seeders/ModuleSeeder.php' => 'database/seeders/CustomerOrdersModuleSeeder.php',
            'config/module.php' => 'config/customer-orders.php',
            'resources/css/module.css' => 'resources/css/customer-orders.css',
            'resources/ts/services/module-api.ts' => 'resources/ts/services/customer-orders-api.ts',
            'resources/ts/components/ModuleBadge.vue' => 'resources/ts/components/CustomerOrdersBadge.vue',
        ] as $original => $generated) {
            self::assertFileDoesNotExist($target.'/'.$original);
            self::assertFileExists($target.'/'.$generated);
        }
        foreach (['routes/api.php', 'routes/web.php', 'resources/views/index.blade.php',
            'resources/ts/register.ts', 'resources/ts/routes.ts', 'resources/ts/store.ts',
            'resources/ts/components/pages/Index.vue', 'nbo.ts', 'vite.config.mts',
            '.github/workflows/tests.yml', '.github/workflows/release.yml', '.github/scripts/publish-release.cjs',
        ] as $file) {
            self::assertFileExists($target.'/'.$file);
        }

        self::assertStringContainsString('NBO_CUSTOMER_ORDERS_MODULE_ENABLED', file_get_contents($target.'/config/customer-orders.php'));
        self::assertStringContainsString('class CustomerOrdersController', file_get_contents($target.'/src/Http/Controllers/CustomerOrdersController.php'));
        $routes = file_get_contents($target.'/routes/api.php');
        self::assertStringContainsString('use NeoPayment\\CustomerOrders\\Http\\Controllers\\CustomerOrdersController;', $routes);
        self::assertStringContainsString("[CustomerOrdersController::class, 'index']", $routes);
        self::assertStringContainsString("[CustomerOrdersController::class, 'show']", $routes);
        self::assertStringContainsString('namespace NeoPayment\\CustomerOrders\\Providers;', file_get_contents($target.'/src/Providers/NboCustomerOrdersServiceProvider.php'));
        self::assertStringContainsString("../css/customer-orders.css", file_get_contents($target.'/resources/ts/register.ts'));
        $this->assertFullyRendered($target);
        self::assertStringContainsString('NBO module created successfully.', $this->command->getDisplay());
        self::assertStringContainsString('Path:             '.$target, $this->command->getDisplay());
    }

    public function testCustomOptionsAreAppliedToGeneratedFiles(): void
    {
        $target = $this->workspace.'/nested/custom-module';
        self::assertSame(Command::SUCCESS, $this->command->execute([
            'code' => 'sales_reports',
            '--path' => $target.'/',
            '--name' => 'Sales Dashboard',
            '--composer-vendor' => 'AcmeTools',
            '--npm-scope' => '@acme/',
            '--namespace' => '\\Acme\\Modules\\',
            '--github-org' => 'AcmeTools',
        ]));

        $composer = $this->readJson($target.'/composer.json');
        $npm = $this->readJson($target.'/package.json');
        self::assertSame('acme-tools/nbo-sales-reports', $composer['name']);
        self::assertSame('Sales Dashboard', $composer['extra']['nbo-module']['name']);
        self::assertSame('src/', $composer['autoload']['psr-4']['Acme\\Modules\\SalesReports\\']);
        self::assertSame('@acme/nbo-sales-reports', $npm['name']);
        self::assertSame('Sales Dashboard', $npm['nbo']['name']);
        self::assertStringContainsString('namespace Acme\\Modules\\SalesReports\\Providers;', file_get_contents($target.'/src/Providers/NboSalesReportsServiceProvider.php'));
        self::assertStringContainsString('acme-tools/nbo-sales-reports', file_get_contents($target.'/README.md'));
        $this->assertFullyRendered($target);
    }

    public function testExistingDirectoryIsPreservedWithoutForce(): void
    {
        $target = $this->workspace.'/existing';
        (new Filesystem())->mkdir($target);
        file_put_contents($target.'/composer.json', 'original content');

        self::assertSame(Command::FAILURE, $this->command->execute(['code' => 'customers', '--path' => $target]));
        self::assertSame('original content', file_get_contents($target.'/composer.json'));
        self::assertFileDoesNotExist($target.'/package.json');
        self::assertStringContainsString('Target directory already exists: '.$target, $this->command->getDisplay());
        self::assertStringContainsString('Use --force', $this->command->getDisplay());
    }

    public function testForceOverwritesGeneratedFilesAndPreservesUnrelatedFiles(): void
    {
        $arguments = ['code' => 'customers', '--path' => $this->workspace.'/existing'];
        self::assertSame(Command::SUCCESS, $this->command->execute($arguments));
        $target = $arguments['--path'];
        file_put_contents($target.'/composer.json', 'modified metadata');
        file_put_contents($target.'/src/Http/Controllers/CustomersController.php', 'modified controller');
        file_put_contents($target.'/keep.txt', 'user content');

        self::assertSame(Command::SUCCESS, $this->command->execute($arguments + ['--force' => true]));
        self::assertSame('neopayment/nbo-customers', $this->readJson($target.'/composer.json')['name']);
        self::assertStringContainsString('class CustomersController', file_get_contents($target.'/src/Http/Controllers/CustomersController.php'));
        self::assertSame('user content', file_get_contents($target.'/keep.txt'));
    }

    public function testGithubActionsCanBeDisabled(): void
    {
        self::assertSame(Command::SUCCESS, $this->command->execute(['code' => 'customers', '--no-github-actions' => true]));
        self::assertDirectoryDoesNotExist($this->workspace.'/nbo-customers/.github');
        self::assertFileExists($this->workspace.'/nbo-customers/composer.json');
    }

    public function testModuleCodeIsRequired(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "code")');
        $this->command->execute([]);
    }

    private function readJson(string $path): array
    {
        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function assertFullyRendered(string $target): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            self::assertStringEndsNotWith('.stub', $file->getFilename());
            self::assertDoesNotMatchRegularExpression('/\{\{[A-Z_]+\}\}/', file_get_contents($file->getPathname()), $file->getPathname());
        }
    }
}
