<?php

namespace App\Plugins\Interfaces;

/*
 * Backwards-compatibility shim. PluginInterface moved to App\Plugins\PluginInterface
 * (flattened per the plugin SDK convention). Kept for one beta cycle so published
 * plugins importing the old namespace keep resolving. Remove before 4.0 GA.
 */
class_alias(\App\Plugins\PluginInterface::class, \App\Plugins\Interfaces\PluginInterface::class);
