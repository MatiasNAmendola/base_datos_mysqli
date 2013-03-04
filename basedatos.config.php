<?php

class perfilBaseDatos
{
    public static $nombre = 'concesionaria';
    public static $usuario = 'root';
    public static $clave = '';
    public static $servidor = 'localhost';
    public static $codificacion = 'utf8';
    // Auto-restablece las condiciones cuando se intenta establecer una nueva
	// Luego de una acción con la base de datos. Se recomienda dejarlo en true
    public static $autorestablecer = true;
	public static $transacciones = true;
}