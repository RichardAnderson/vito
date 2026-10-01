<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Component Functions
    |--------------------------------------------------------------------------
    |
    | What a component is capable of, beyond its type. Plugins extend this
    | catalogue through App\Plugins\RegisterComponentFunction. `unlocks` names
    | the component surfaces the function makes available.
    |
    */

    'functions' => [
        'http' => [
            'label' => 'Web',
            'description' => 'Serves this environment\'s domains over HTTP.',
            'color' => 'info',
            'unlocks' => ['domains'],
        ],
        'processes' => [
            'label' => 'Processes',
            'description' => 'Runs long-lived background processes, managed by Supervisor.',
            'color' => 'purple',
            'unlocks' => ['workers'],
        ],
        'scheduler' => [
            'label' => 'Scheduler',
            'description' => 'Runs this component\'s scheduled commands from cron.',
            'color' => 'blue',
            'unlocks' => ['cron_jobs'],
        ],
    ],
];
