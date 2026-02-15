<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        return view('home', [
            'pageTitle'       => 'ValoraNL | Inteligencia de datos inmobiliarios en Nuevo León',
            'metaDescription' => 'ValoraNL centraliza listings, normaliza datos y estima el valor de mercado de propiedades en Nuevo León.',
        ]);
    }
}
