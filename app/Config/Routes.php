<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index', ['as' => 'home.index']);
$routes->post('/valuacion/estimar', 'Home::estimate', ['as' => 'valuation.estimate']);
$routes->get('/propiedades', 'Listings::index', ['as' => 'listings.index']);
