#!/bin/bash
VERSION=0.0.4

docker build -t goevexx/toggl-invoiceninja-sync:$VERSION .
docker build -t goevexx/toggl-invoiceninja-sync:latest .

docker push goevexx/toggl-invoiceninja-sync:$VERSION
docker push goevexx/toggl-invoiceninja-sync:latest
