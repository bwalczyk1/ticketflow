<?php

return [
    // CLAUDE.md only imports AGENTS.md, so the generated guidelines belong in AGENTS.md.
    'agents' => [
        'claude_code' => [
            'guidelines_path' => 'AGENTS.md',
        ],
    ],

    'skills' => [
        'exclude' => ['deploying-to-cloud'],
    ],
];
