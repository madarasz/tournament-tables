<?php

declare(strict_types=1);

return [
    // Path to Chrome/Chromium binary
    // macOS: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
    // Linux: '/usr/bin/chromium-browser' or '/usr/bin/google-chrome'
    // Windows: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
    'chromePath' => null, // null = auto-detect

    // Headless mode options
    'headless' => true,
    'noSandbox' => true, // Required for Docker environments

    // Timeouts (in milliseconds)
    'navigationTimeout' => 30000,
    'pageLoadDelay' => 2000, // Wait for React hydration
];
