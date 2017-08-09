#!/bin/bash

docker login -u="$QUAY_USERNAME" -p="$QUAY_PASSWORD" quay.io
docker tag keboola/adwords-extractor quay.io/keboola/adwords-extractor:$TRAVIS_TAG
docker images
docker push quay.io/keboola/adwords-extractor:$TRAVIS_TAG

export APP_ID=keboola.ex-adwords-v201705
docker pull quay.io/keboola/developer-portal-cli-v2:latest
export REPOSITORY=`docker run --rm -e KBC_DEVELOPERPORTAL_USERNAME=$KBC_DEVELOPERPORTAL_USERNAME -e KBC_DEVELOPERPORTAL_PASSWORD=$KBC_DEVELOPERPORTAL_PASSWORD -e KBC_DEVELOPERPORTAL_URL=$KBC_DEVELOPERPORTAL_URL quay.io/keboola/developer-portal-cli-v2:latest ecr:get-repository keboola $APP_ID`
docker tag keboola/$APP_ID:latest $REPOSITORY:$TRAVIS_TAG
docker tag keboola/$APP_ID:latest $REPOSITORY:latest
eval $(docker run --rm -e KBC_DEVELOPERPORTAL_USERNAME=$KBC_DEVELOPERPORTAL_USERNAME -e KBC_DEVELOPERPORTAL_PASSWORD=$KBC_DEVELOPERPORTAL_PASSWORD -e KBC_DEVELOPERPORTAL_URL=$KBC_DEVELOPERPORTAL_URL quay.io/keboola/developer-portal-cli-v2:latest ecr:get-login keboola $APP_ID)
docker push $REPOSITORY:$TRAVIS_TAG
docker push $REPOSITORY:latest