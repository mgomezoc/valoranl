<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index', ['as' => 'home.index']);
$routes->get('/robots.txt', 'Home::robots', ['as' => 'seo.robots']);
$routes->post('/valuacion/estimar', 'Home::estimate', ['as' => 'valuation.estimate']);
$routes->get('/sitemap.xml', 'Home::sitemap', ['as' => 'seo.sitemap']);
$routes->get('/propiedades', 'Listings::index', ['as' => 'listings.index']);
$routes->get('/propiedades/(:num)', 'Listings::show/$1', ['as' => 'listings.show']);
