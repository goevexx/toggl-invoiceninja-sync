[![SensioLabsInsight](https://insight.sensiolabs.com/projects/c75bd15a-5d40-4879-9a2f-23e4a6b683e0/mini.png)](https://insight.sensiolabs.com/projects/c75bd15a-5d40-4879-9a2f-23e4a6b683e0)
[![Build Status](https://travis-ci.org/Matth--/toggl-invoiceninja-sync.svg?branch=master)](https://travis-ci.org/Matth--/toggl-invoiceninja-sync)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Matth--/toggl-invoiceninja-sync/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Matth--/toggl-invoiceninja-sync/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/Matth--/toggl-invoiceninja-sync/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Matth--/toggl-invoiceninja-sync/?branch=master)

# Invoice syncer
This application is built to synchronize loggings from toggl to invoiceninja. 

Set the correct parameters in config/parameters.yml

## Original Installation

### Installation

**Attention**: Your php version need to be 7.0 < *your version* < 7.4. So rather run [docker](#docker-setup).

- Clone or download the repo
- Use the latest tagged release
- run `composer install`
  
### Configuration

Now fill in the parameters in config/parameters.yml
```yaml
parameters:
    debug: false
    serializer.config_dir: '%kernel.root_dir%/config/serializer'
    serializer.cache_dir: '%kernel.root_dir%/cache'

    toggl.api_key: KEY
    toggl.toggl_base_uri: https://api.track.toggl.com/api/
    toggl.reports_base_uri: https://api.track.toggl.com/reports/api/
    toggl.timings.round: true

    invoice_ninja.base_uri: https://invoicing.co/api/
    invoice_ninja.api_key: KEY

    # Key = name in toggl (Has to be correct)
    # Value = client id from invoiceninja
    clients:
         client_name: abc1

    # Key = name in toggl
    # Value= id from invoiceninja
    projects:
         first_project: abc12
         second_project: abc123

    # Key = name in toggl
    # Value= id from invoiceninja
    users.enabled: true
    users:
         first_user: abc12
         second_user: abc123

    
    time.round.minutes: 0
    toggl.billable_only: true
```

The key-value pairs in the `clients` variable are important. The key should be the **exact** client name from toggl. The value should be the client id from invoiceninja.
If the time entry was matched with the `clients` variable it skips the part where it checks the `projects` variable. This varialbe acts the same way as the `clients
 variable but instead of matching the client name, it matches the **exact** project name.

The key-value pairs in users are destined to choose the correct user in invoiceninja creating a task. If the user from toggl doesn't match with the corresponding userid from invoiceninja, then it will fail.  

### Run the command

to run the command just run:

Create and update tasks from time entries and update
```bash
php syncer sync:timings --since='dd.mm.yyyy' --until='dd.mm.yyyy'
```

Delete timings
```bash
php syncer sync:delete --since='dd.mm.yyyy' --until='dd.mm.yyyy'
```

Remove reference from toggl logs, which do no longer exist in invoice ninja.
```bash
php syncer sync:clean
```

### Run as cronjob

As this command syncs the tasks from the current day, this cronjob setting will run the command daily at 23:55.

```bash
55 23 * * * /path/to/php /path/to/syncer sync:timings
```

## Docker Setup
Get the docker image 
```bash
docker pull goevexx/toggl-invoiceninja-sync
```
Run it. This only works if you mount [config/parameters.yml](#configuration).

```bash
docker run --rm --name 'tgl-in-sync' -it -v /absolute/path/to/parameters.yml:/syncer/config/parameters.yml goevexx/toggl-invoiceninja-sync sync:timings
```

You can also cron job this execution. See [Run as cronjob](#run-as-cronjob)

## Fork Contribution

Added extra functionality to the sync:
- Round duration of time log to variable minutes
- Make only billable entries sync
- Select date range to select time entries to sync
- Sync only not yet synced time entries
  - Adds id tag in toggl on sync
  - Puts id in custom_value1 in invoice ninja
  - Puts worker name fro toggl in custom_value2 in invoice ninja
- Updates task on sync
- Use docker to run
- Improved logging
- Added deletion, and cleanup as functionality
   
 See `parameters.yaml.dist` or `php syncer sync:timings --help`
