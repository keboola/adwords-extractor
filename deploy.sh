#!/bin/bash

docker login -e="." -u="$QUAY_USERNAME" -p="$QUAY_PASSWORD" quay.io
docker tag keboola/adwords-extractor quay.io/keboola/adwords-extractor:$TRAVIS_TAG
docker images
docker push quay.io/keboola/adwords-extractor:$TRAVIS_TAG