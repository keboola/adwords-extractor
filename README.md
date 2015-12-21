# adwords-extractor
KBC Docker app for extracting data from Google AdWords

The Extractor gets list of accessible clients, list of their campaigns and defined AWQL queries for given date range and saves the data to Storage API.

## Status

[![Build Status](https://travis-ci.org/keboola/adwords-extractor.svg)](https://travis-ci.org/keboola/adwords-extractor) [![Code Climate](https://codeclimate.com/github/keboola/adwords-extractor/badges/gpa.svg)](https://codeclimate.com/github/keboola/adwords-extractor) [![Test Coverage](https://codeclimate.com/github/keboola/adwords-extractor/badges/coverage.svg)](https://codeclimate.com/github/keboola/adwords-extractor/coverage)

## Configuration

- **parameters**:
    - **developer_token** - Your developer token
    - **refresh_token** - Generated refresh token
    - **bucket** - Name of bucket where the data will be saved
    - **since** *(optional)* - start date of downloaded stats (default is "-1 day")
    - **until** *(optional)* - end date of downloaded stats (default is "-1 day")
