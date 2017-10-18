<?php

$configuration = simplexml_load_file("configuration.xml");

define("BASE_CURRENCY", $configuration->base);

define("CURRENCIES_FILE", $configuration->data->currencies);
define("CURRENCIES_SOURCE", $configuration->sources->currencies);

define("RATES_FILE", $configuration->data->rates);
define("RATES_SOURCE", $configuration->sources->rates);

define("CURRENCIES", explode(", ", $configuration->currencies));