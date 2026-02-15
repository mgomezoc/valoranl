<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index', ['as' => 'home.index']);
$routes->get('/propiedades/(:num)', 'Home::show/$1', ['as' => 'listing.show']);
