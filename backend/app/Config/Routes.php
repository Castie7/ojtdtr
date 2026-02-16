<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// 1. Enable CORS Filter Globally (See "The Glue" section below)
// We group everything under 'api' and apply a 'cors' filter to avoid "Access Denied" errors.
$routes->group('api', ['filter' => 'cors'], function ($routes) {
    
    // Group: DTR Actions
    $routes->group('dtr', function($routes) {
        $routes->get('stats/(:num)', 'Api\Dtr::stats/$1');   // Get Stats
        $routes->get('logs/(:num)', 'Api\Dtr::logs/$1');     // Get Logs
        $routes->post('clockIn', 'Api\Dtr::clockIn');        // Clock In
        $routes->post('clockOut', 'Api\Dtr::clockOut');      // Clock Out
        $routes->post('editLog', 'Api\Dtr::editLog');        // Edit Log
        $routes->post('importCsv', 'Api\Dtr::importCsv');    // Import CSV
        $routes->get('reset-data', 'Api\Dtr::resetData');    // Reset Data
    });

    // Group: Auth (Future proofing)
    $routes->post('login', 'Api\Auth::login');
    
    // Handle "Preflight" requests (Essential for Vue+CI4)
    // Browsers send an OPTIONS request before POST to check permission.
    $routes->options('(:any)', function() {}); 
});

// Catch-all for non-API routes (Optional: returns JSON 404)
$routes->set404Override(function() {
    return response()->setJSON(['error' => 'Endpoint not found'])->setStatusCode(404);
});