<?php

/**
 * Rutas del sistema: index.php?url=clave → controlador.
 * Los controladores se cargan con el autoload PSR-4 de Composer.
 */

use App\Controllers\AnalisisController;
use App\Controllers\AutenticatorController;
use App\Controllers\BarberosController;
use App\Controllers\BitacoraController;
use App\Controllers\BuzonController;
use App\Controllers\CategoriasController;
use App\Controllers\CierresCajaController;
use App\Controllers\CitasController;
use App\Controllers\ClientesController;
use App\Controllers\CursosController;
use App\Controllers\DashboardController;
use App\Controllers\GastosController;
use App\Controllers\InscripcionesController;
use App\Controllers\MetodosPagoController;
use App\Controllers\NominaController;
use App\Controllers\PagosController;
use App\Controllers\PerfilController;
use App\Controllers\PoliticasController;
use App\Controllers\ProductosController;
use App\Controllers\PromocionesController;
use App\Controllers\PublicoController;
use App\Controllers\RetirosController;
use App\Controllers\RolesController;
use App\Controllers\ServiciosController;
use App\Controllers\TicketsController;
use App\Controllers\UsuariosController;

return [
    // Acceso y cuenta
    'autenticator' => AutenticatorController::class,
    'dashboard' => DashboardController::class,
    'perfil' => PerfilController::class,
    'publico' => PublicoController::class,

    // Atención
    'citas' => CitasController::class,
    'tickets' => TicketsController::class,
    'pagos' => PagosController::class,

    // Catálogos
    'clientes' => ClientesController::class,
    'barberos' => BarberosController::class,
    'servicios' => ServiciosController::class,
    'productos' => ProductosController::class,
    'categorias' => CategoriasController::class,
    'promociones' => PromocionesController::class,
    'metodos_pago' => MetodosPagoController::class,

    // Caja y finanzas
    'cierres' => CierresCajaController::class,
    'gastos' => GastosController::class,
    'retiros' => RetirosController::class,
    'nomina' => NominaController::class,

    // Academia
    'cursos' => CursosController::class,
    'inscripciones' => InscripcionesController::class,

    // Análisis
    'buzon' => BuzonController::class,
    'analisis' => AnalisisController::class,

    // Página pública
    'politicas' => PoliticasController::class,

    // Seguridad
    'usuarios' => UsuariosController::class,
    'roles' => RolesController::class,
    'bitacora' => BitacoraController::class,
];
