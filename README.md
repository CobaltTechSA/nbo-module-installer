# NBO Installer

NBO Installer is a CLI tool used to generate new NBO modules with a standard Laravel + Vue + Vite structure.

It creates the basic backend and frontend scaffolding required for a module to integrate with `nbo-core`.

## Features

- Generates a complete NBO module structure.
- Creates Laravel package files.
- Creates NPM package metadata.
- Adds frontend module registration files.
- Adds Vue Router routes.
- Adds Vuex store module.
- Adds basic Vue pages.
- Adds Laravel routes, controller, config, migration and seeder stubs.
- Prepares the module to be installed as a Composer dependency in an NBO host application.

## Requirements

- PHP 8.3 or higher
- Composer
- Node.js / NPM
- Git
- Access to the GitHub repository where the installer is hosted

## Installation

### Install globally from GitHub

If the installer repository is private, first register the repository in Composer:

```bash
composer global config repositories.nbo-installer vcs git@github.com:CobaltTechSA/nbo-installer.git
```
Then install it globally:

```bash
composer global require neopayment/nbo-installer:dev-main
```

Make sure Composer’s global vendor/bin directory is in your PATH.

You can check Composer’s global vendor path with:
```bash
composer global config bin-dir --absolute
```

For example, on Linux it may be:
```bash
~/.config/composer/vendor/bin
```
Add it to your shell configuration if needed:
```bash
export PATH="$PATH:$HOME/.config/composer/vendor/bin"
```

Then reload your shell:
```bash
source ~/.bashrc
```

Verify the installation:
```bash
nbo list
```
You should see the `module:new` command.

## Usage
Create a new module:
```bash
nbo module:new <code> [options]
```
Example:
```bash
nbo module:new customers
```
This will generate a directory named:
```
nbo-customers
```

The generated module will use the following conventions:
```
Module code:      customers
Composer package: neopayment/nbo-customers
NPM package:      @neopayment/nbo-customers
PHP namespace:    NeoPayment\Customers
```
Example with all options:
```bash
nbo module:new customers \
  --name="Customers" \
  --path=./nbo-customers \
  --composer-vendor=neopayment \
  --npm-scope=@neopayment \
  --namespace=NeoPayment \
  --github-org=CobaltTechSA
```

#### Options

| Option              | Description                                                 | Default                 |
| ------------------- | ----------------------------------------------------------- |-------------------------|
| `code`              | Module code, for example `customers`, `transactions`, `acs` | Required                |
| `--name`            | Human-readable module name                                  | Generated from the code |
| `--path`            | Target path where the module will be created                | `./nbo-{code}`          |
| `--composer-vendor` | Composer vendor name                                        | `neopayment`            |
| `--npm-scope`       | NPM scope                                                   | `@neopayment`           |
| `--namespace`       | PHP root namespace                                          | `NeoPayment`            |
| `--github-org`      | GitHub organization name                                    | `CobaltTechSA`          |
| `--force`           | Overwrite existing files                                    | Disabled                |

### Generated structure
A generated module will have a structure similar to this:
```
nbo-customers/
├── composer.json
├── package.json
├── README.md
├── config/
│   └── customers.php
├── src/
│   └── Http/
│       ├── Controllers/
│       │   └── CustomersController.php
│       └── Providers/
│           └── NboCustomersServiceProvider.php
├── routes/
│   ├── api.php
│   └── web.php
├── database/
│   └── seeders/
│       └── CustomersModuleSeeder.php
└── resources/
    ├── views/
    │   └── index.blade.php
    ├── css/
    │   └── customers.css
    └── ts/
        ├── register.ts
        ├── routes.ts
        ├── store.ts
        ├── services/
        │   └── customers-api.ts
        └── components/
            └── CustomersBadge.vue
            └── pages/
                ├── Index.vue
                ├── Create.vue
                └── Show.vue
```
### Generated backend integration

Each generated module is a Laravel package.

The generated composer.json includes:

```json
{
  "name": "neopayment/nbo-customers",
  "description": "Customers module for NBO",
  "type": "library",
  "license": "proprietary",
  "require": {
    "php": "^8.3",
    "neopayment/nbo-core": "^1.0",
    "spatie/laravel-package-tools": "^1.93"
  },
  "autoload": {
    "psr-4": {
      "NeoPayment\\Customers\\": "src/",
      "NeoPayment\\Customers\\Database\\Seeders\\": "database/seeders/"
    }
  },
  "classmap": [
    "database/seeders"
  ],
  "extra": {
    "laravel": {
      "providers": [
        "NeoPayment\\Customers\\Providers\\NboCustomersServiceProvider"
      ]
    },
    "nbo-module": {
      "code": "customers",
      "name": "Customers",
      "seeder": "NeoPayment\\Customers\\Database\\Seeders\\CustomersModuleSeeder",
      "vite": {
          "laravel": {
              "input": [
                  "resources/css/customers.css"
              ]
          }
      }
    }
  },
  "minimum-stability": "stable",
  "prefer-stable": true
}
```
The service provider registers:

- config 
- views
- web routes
- api routes
- migrations
- package migrations auto-run support

### Generated frontend integration

Each generated module is also an NPM package.

The generated `package.json` includes:
```json
{
  "name": "@neopayment/nbo-customers",
  "version": "1.0.0",
  "private": true,
  "type": "module",
  "exports": {
    "./register": "./resources/ts/register.ts"
  },
  "nbo": {
    "type": "module",
    "code": "customers",
    "name": "Customers",
    "frontend": {
      "register": "./register"
    }
  },
  "peerDependencies": {
    "@neopayment/nbo-core": "^1.0.0",
    "vue": "^3.5.0",
    "vue-router": "^4.5.0",
    "vuex": "^4.1.0",
    "primevue": "^4.3.0"
  },
  "devDependencies": {
    "@neopayment/nbo-core": "^1.0.0",
    "typescript": "^5.0.0",
    "vue": "^3.5.0",
    "vue-router": "^4.5.0",
    "vuex": "^4.1.0",
    "primevue": "^4.3.0"
  }
}
```

The `register.ts` file is the frontend entrypoint for the module.

It is automatically detected by the nbo-core Vite plugin and is responsible for registering:

- Vue routes
- Vuex store module
- CSS
- global components if needed

Generated modules should not create their own Vue app, router, or store. Those are owned by nbo-core.

### After generating a module

Enter the generated module directory:
```bash
cd nbo-customers
```
Install dependencies:
```bash
composer install
npm install
```
Initialize git:
```bash
git init
git add .
git commit -m "Initial module scaffold"
```

Create a GitHub repository for the module, for example:
```
git@github.com:CobaltTechSA/nbo-customers.git
```

Then push:
```bash
git branch -M main
git remote add origin git@github.com:CobaltTechSA/nbo-customers.git
git push -u origin main
```

The install the module:
```bash
composer require neopayment/nbo-customers:dev-main
npm install
php artisan optimize:clear
php artisan migrate
php artisan db:seed
```

### Updating the global installer
If the installer was installed globally, update it with:
```bash
composer global update neopayment/nbo-installer
```

### Troubleshooting

#### `nbo: command not found`
Composer’s global `vendor/bin` directory is probably not in your `PATH`.

Check the global bin path:
```bash
composer global config bin-dir --absolute
```
Add that path to your shell profile.

#### The command is not listed
Run:
```bash
composer global dump-autoload
```
Then verify:
```bash
nbo list
```

#### The target directory already exists
Use another path:
```bash
nbo module:new customers --path=./alternative/path/nbo-customers
```
Or overwrite existing files:
```bash
nbo module:new customers --force
```

### Recommended workflow
```bash
nbo module:new customers
cd nbo-customers

composer install
npm install

git init
git add .
git commit -m "Initial customers module"
git branch -M main
git remote add origin git@github.com:CobaltTechSA/nbo-customers.git
git push -u origin main
```

Then install it in the main project. Example `nbo-demo`:
```bash
cd nbo-demo
composer require neopayment/nbo-customers:dev-main
npm install
php artisan migrate
php artisan db:seed
npm run dev
```