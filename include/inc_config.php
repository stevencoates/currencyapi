<?php

$configuration = simplexml_load_file("configuration.xml");

define("BASE_CURRENCY", $configuration->base);
define("CURRENCIES_FILE", $configuration->currencies);
define("RATES_FILE", $configuration->rates);