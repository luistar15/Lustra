<?php

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\DowngradeLevelSetList;

return static function ( RectorConfig $config ): void {
	$config->indent( "\t", 1 );
	$config->sets( [ DowngradeLevelSetList::DOWN_TO_PHP_73 ] );
	$config->paths( [ dirname( __DIR__ ) . '/src' ] );
};
