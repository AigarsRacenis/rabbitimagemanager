# Project Name

Short description of the project.

## Requirements
* Magento 2.3.x
* PHP 8.1
* NodeJS 16
* Mysql 8.0
* Elasticsearch

Any project specifics...
## Table of Contents

- [About](#about)
- [Features](#features)
- [Installation](#installation)
- [Hints](#hints)
- [Usage](#usage)
- [Git Workflow](#git-workflow)

## About

Briefly describe what your project does and its purpose.

## Features

List the key features of your Magento 2 project.

## Installation

 Please note any extra feature like styles compilation
 or other project specific things.

1. **Clone the repository**

    ```bash
    git clone https://github.com/your-username/your-repo.git
    ```

2. **Install Dependencies**

    ```bash
    cd your-project-folder
    d/composer install
    ```

3. **Enable the Module**

    ```bash
    d/magento module:enable Vendor_Module
    ```

4. **Run Magento Setup Upgrade Command**

    ```bash
    d/magento setup:upgrade
    ```

5. **Deploy Static Content**

    ```bash
    d/magento setup:static-content:deploy
    ```

6. **Flush Cache**

    ```bash
    d/magento cache:flush
    ```

## Hints

Please note any things that needs to be taken into consideration when setting up project.

## Usage

Explain how to use your Project

## Git Workflow

| Branch             | Usage                                                                                                                                     |
|--------------------|-------------------------------------------------------------------------------------------------------------------------------------------|
| `feature/<ticket>` | Branch from `master` for new feature implementation. Once deployed to production can be deleted                                           |
| `bugfix/<ticket>`  | Branch from `master` to fix any issue related to task after it has been deployed to production                                            | 
| `development`      | Development environment - Contains changes for completed or partially completed tickets - testing ground before pushing it further for CR |
| `staging`          | Staging environment - Currently most up to date changes until pre-live is created                                                         |
| `release`          | Temporary branch to gather up all the changes required for the deployment to prelive                                                      |
| `master`           | Prelive environment                                                                                                                       |

---

Due to branch permissions, conflicts that arise need to be resolved on separate branches. Upon merging branch with
conflicts resolved, it should be closed.

| Branch                            | Usage                                    |
|-----------------------------------|------------------------------------------|
| `feature/<ticket>-PR-development` | Used when merging changes to development |
| `feature/<ticket>-PR-staging`     | Used when merging changes to staging     |

When merging changes from `release` to `master`, there shouldn't be any conflicts

### Commits

Commit subject has to contain ticket id and description should be in imperative mood

    MB-1000 Remove unnecessary stylesheets
