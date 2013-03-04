<?php
class basedatos
{
	// Se definen las propiedades a usar
    private static $_instancia = array();
    private $nombre_instancia;
    private $ruta_principal;
    private $conexion;
    private $magic_quotes_gpc;
    private $resultado;
    private $bd_servidor;
    private $bd_usuario;
    private $bd_clave;
    private $bd_nombre;
    private $bd_codificacion;
    private $tabla;
    private $clave_unica = array();
    private $clave_primaria = array();
    private $auto_incrementable;
    private $campos = array();
    private $campos_invertidos = array();
    private $predeterminados = array();
    private $union = array();
    private $orden = array();
    private $grupo = array();
    private $aleatorio = false;
    private $sql_campos = array();
    private $sql_condicion = array();
    private $sql_completo = array();
    private $se_establecio = false;
    private $reinicio_autom = true;
    private $alias = array();
    private $diferente = '';
    private $tabla_alias = array();
	private $transacciones = false;
	private $error_trans = array();

	/** 
     *  Método estático, crea una instancia de una TABLA para su posterior uso.
	 *
	 *	Ejemplo de uso:
	 *
	 *	$objeto = basedatos::tabla('usuarios');
     *
     *  @access public
	 *	@method object tabla( string $tabla [, array $conexion] )
	 *	@param string $tabla (requerido)
	 *	@param array $conexion (opcional)
	 *	@return instance or object
	 *
	 *	En el parámetro `tabla` se debe ingresar el nombre de la tabla (requerido).
	 *	En el parametro `conexion` se deben ingresar los datos como en el archivo 
	 *	de configuración predeterminado.
	 *	@link basedatos.config.php
     */
    public static function tabla($tabla = '', $conexion = false)
    {
        include_once (dirname(__file__) . '/basedatos.config.php');
		
        if (is_array($conexion))
        {
            $bd_usuario = $conexion['usuario'];
            $bd_clave = $conexion['clave'];
            $bd_nombre = $conexion['nombre'];
            $bd_servidor = isset($conexion['servidor']) ? $conexion['servidor'] : 'localhost';
            $bd_codificacion = isset($conexion['codificacion']) ? $conexion['codificacion'] : 'utf8';
        } else
        {
            $bd_usuario = perfilBaseDatos::$usuario;
            $bd_clave = perfilBaseDatos::$clave;
            $bd_nombre = perfilBaseDatos::$nombre;
            $bd_servidor = perfilBaseDatos::$servidor;
            $bd_codificacion = perfilBaseDatos::$codificacion;
        }
		// Genera el nombre de la instancia a partir de la concatenación de las variables de conexión
		// encriptando su resultado con sha1
        $nombre_instancia = sha1($tabla . $bd_usuario . $bd_clave . $bd_nombre . $bd_servidor);

        if (!isset(self::$_instancia[$nombre_instancia]) or null === self::$_instancia[$nombre_instancia])
        {
            self::$_instancia[$nombre_instancia] = new self($tabla, $bd_usuario, $bd_clave, $bd_nombre, $bd_servidor, $bd_codificacion);
            self::$_instancia[$nombre_instancia]->nombre_instancia = $nombre_instancia;
        }
        return self::$_instancia[$nombre_instancia];
    }
	
	/** 
     *  Método constructor
     * 
     *  @access private
     */
    private function __construct($tabla, $bd_usuario, $bd_clave, $bd_nombre, $bd_servidor, $bd_codificacion)
    {
        $this->magic_quotes_gpc = get_magic_quotes_gpc();
        $this->ruta_principal = dirname(__file__);
		
        if (strpos($bd_servidor, ':') !== false)
        {
            list($servidor, $bd_puerto) = explode(':', $bd_servidor, 2);
            $this->conexion = new mysqli($servidor, $bd_usuario, $bd_clave, $bd_nombre, $bd_puerto);
        } else
            $this->conexion = new mysqli($bd_servidor, $bd_usuario, $bd_clave, $bd_nombre);
        
		if (!$this->conexion)
            $this->error('Error de Conexión. No se logró conectar a la base de datos.');
		
		// Ajusta la codificación para leer la base de datos
        $this->conexion->set_charset($bd_codificacion);
		
        if ($this->conexion->error)
            $this->error($this->conexion->error);
			
        if ($tabla)
            $this->tabla = $tabla;
			
        $this->reinicio_autom = perfilBaseDatos::$autorestablecer;
    }

	/** 
     *  Método de clonado
     *
     *  @access private 
     */
    private function __clone()
    {
    }
	
	/** 
     *  Método estático, crea una instancia de Conexión para su posterior uso
	 *	(sin definir el enlace a una tabla específica).
	 *	Se utiliza para trabajar con consultas SQL personalizadas.
     *
     *  @access public
	 *	@method object nueva_conexion( [array $parametros] );
	 *	@param array $parametros (opcional)
	 *	@return instance
	 *	@example $parametros = array('servidor'=>'localhost','usuario'=>'user','clave'=>'pass','nombre'=>'base','codificacion'=>'utf8');
	 *
	 *	Ejemplo de uso:
	 *	$objeto = basedatos::nueva_conexion($parametros);
     */
    public static function nueva_conexion($parametros = false)
    {
        return self::tabla(false, $parametros);
    }
	
	/** 
     *  Método que establece el tipo de reset
	 *
     *	@access public
	 *	@method object reajusteAutomatico( [bool $booleano] );
	 *	@param bool $booleano (opcional)
	 *  @return instance
     */
    public function reseteo_automatico($booleano = true)
    {
        $this->reinicio_autom = $booleano;
		
        return $this;
    }
	
	/** 
     *  Determina la existencia de errores.
	 *	Con el uso de transacciónes, llamamos al método ejecutar
	 *	y éste nos advierte sobre la existencia de errores.
	 *	Sin embargo, cuando trabajamos sin transacciones, desde
	 *	este método es posible determinar si hay errores luego de
	 *	realizada la consulta.	 
	 *  
     *	@access public
	 *	@method bool hay_error( void );
	 *  @return bool
     */
    public function hay_error()
    {
		return $this->conexion->error ? true : false;
    }
	
	/** 
     *  Permite el inicio y uso de transacciones.
	 *	No requiere parámetros
	 *
     *	@access public
	 *	@method bool iniciar_transacciones( void );
	 *  @return bool
     */
	public function iniciar_transacciones()
	{
		$this->transacciones = $this->habilitar_transacciones();
		
		if ( $this->transacciones ){
			$this->conexion->autocommit(FALSE);
		}	
		$this->error_trans = array();
		
		return $this->transacciones;
	}
	
	/** 
     *  Se debe utilizar cuando trabajamos con transacciones.
	 *	Realiza un commit o rollback al percibir errores.
	 *	En el caso de que estos últimos aparezcan, devuelve false.
	 *
     *	@access public
	 *	@method bool ejecutar( void );
	 *  @return bool
     */
	public function ejecutar()
	{
		$retorno = false;
		
		if ( $this->transacciones ){
			
			if ( sizeof($this->error_trans) == 0 ){
				$this->conexion->commit();
				$retorno = true;
			} else {
			    $this->conexion->rollback(); 
			}
		}
		return $retorno;
	}
	
	/** 
     *  Si se produce un rollback (igual a que el método ejecutar devuelva false)
	 *	se puede obtener el resultado de los errores en un array.
	 *
     *	@access public
	 *	@method mixed errores( void );
	 *  @return mixed
     */
	public function errores()
	{
		$retorno = false;
		
		if ( sizeof($this->error_trans) > 0 )
			$retorno = $this->error_trans;
		
		return $retorno;
	}
	
	/** 
     *  Finaliza las transacciones.
	 *  
     *	@access public
	 *	@method void cerrar_transacciones( void )
	 *  @return void
     */
	public function cerrar_transacciones()
	{
		if ( $this->transacciones ){
			$this->conexion->autocommit(TRUE);
		}
		$this->error_trans = array();
		$this->transacciones = false;
	}
	
	/** 
     *  Verifica la posibilidad de aplicar transacciones.
	 *	No requiere parámetros
	 *
     *	@access public
	 *	@method bool habilitar_transacciones( void );
	 *  @return bool
     */
	public function habilitar_transacciones()
	{
		$resultado_01 = $this->conexion->query("SHOW ENGINES");
		$i = 0;
		
		while ($registro = $resultado_01->fetch_assoc())
        {
            if ( $registro['Engine'] == 'InnoDB' && $registro['Transactions'] == 'YES' )
				$i++;
        }
		
		$resultado_01->free();
		
		if ($i<=0)
			return false;
        
		$resultado_02 = $this->conexion->query("SHOW TABLE STATUS");
		$i = $j = 0;
		
		while ($registro = $resultado_01->fetch_assoc())
        {
            if ( $registro['Engine'] == 'InnoDB' )
				$j++;
				
			$i++;
        }
		
		$resultado_02->free();
		
		if ($i == $j)
			return true;
		else
			return false;
	}
	
	/** 
     *  Devuelve el número de filas o registros en una tabla.
	 *	No requiere parámetros
	 *  
     *	@access public
	 *	@method int total( void )
	 *  @return int
	 *
	 *	Ejemplo de uso:	$usuarios = basedatos::tabla('usuarios');
	 *			 		$total_usuarios = $usuarios->total();
     */
    public function total()
    {
        $union = $this->_preparar_union();
        $desde = $this->_preparar_desde();
        $condicion = $this->_preparar_condicion();
        $grupo = $this->_preparar_grupo();
        //echo "SELECT COUNT(*) as `count` FROM {$desde} {$union} {$condicion} {$grupo}";
        $this->se_establecio = true;
        $resultado = $this->conexion->query("SELECT COUNT({$this->diferente}*) as `count` FROM {$desde} {$union} {$condicion} {$grupo}",
            MYSQLI_USE_RESULT);
        if ($this->conexion->error)
            $this->error($this->conexion->error);
        else
            return (int)$resultado->fetch_object()->count;
    }
	
	/** 
     *  Devuelve las filas de la tabla como una lista de matrices asociativas.
	 *  Se puede determinar el limite o la cantidad de artículos y declarar
	 *	su punto de inicio. Si ambos parámetros no se definen, se
	 *	devolverán todos los registros encontrados.
	 *
     * @access public
	 * @method array obtener_matriz( [int $limite] [, int $inicio] )
     * @param int $limite (opcional)
     * @param int $inicio (opcional)
	 * @return array
	 *
	 *	Ejemplo:	$usuarios = basedatos::tabla('usuarios');
					$detalles = $usuarios->obtener_matriz(10) // Devuelve 10 usuarios
     */
    public function obtener_matriz($limite = 0, $inicio = 0)
    {
        $this->obtener_info_tabla($this->tabla);
        $union = $this->_preparar_union();
        $seleccion = $this->_preparar_seleccion();
        $desde = $this->_preparar_desde();
        $condicion = $this->_preparar_condicion();
        $grupo = $this->_preparar_grupo();
        $orden = $this->_preparar_orden();
        $limite = $this->_preparar_limite($limite, $inicio);
        $salida = array();
        $this->se_establecio = true;
        $resultado = $this->conexion->query("SELECT {$this->diferente}{$seleccion} FROM {$desde} {$union} {$condicion} {$grupo} {$orden} {$limite}",MYSQLI_USE_RESULT);
        // echo "SELECT {$seleccion} FROM {$desde} {$union} {$condicion} {$grupo} {$orden} {$limite}";
        if ($this->conexion->error)
            $this->error($this->conexion->error);
        while ($registro = $resultado->fetch_assoc())
        {
            $salida[] = $registro;
        }
        $resultado->free(); // Libera la memoria del resultado de la consulta
        return $salida;
    }
	
	/** 
     *  Devuelve una fila de la tabla como 'vector' asociativo.
	 *	No tiene parámetros
	 *
     *	@access public
	 *  @method array obtener_un_registro( void )
	 *  @return array
     */
    public function obtener_un_registro()
    {
        $this->obtener_info_tabla($this->tabla);
        $union = $this->_preparar_union();
        $seleccion = $this->_preparar_seleccion();
        $desde = $this->_preparar_desde();
        $condicion = $this->_preparar_condicion();
        $grupo = $this->_preparar_grupo();
        $orden = $this->_preparar_orden();
        $salida = array();
        $this->se_establecio = true;
        $resultado = $this->conexion->query("SELECT {$this->diferente}{$seleccion} FROM {$desde} {$union} {$condicion} {$grupo} {$orden} LIMIT 1",
            MYSQLI_USE_RESULT);
        //echo "SELECT {$seleccion} FROM {$desde} {$union} {$condicion} {$grupo} {$orden} LIMIT 1";
        if ($this->conexion->error)
            $this->error($this->conexion->error);
        return $resultado->fetch_assoc();
    }
	
	/** 
     * Devuelve las filas de la tabla como listas de valores.
	 *
     * @access public
	 * @method array obtener_lista( mixed $campo [, int $limite] [, int $inicio] [, string $tabla] )
     * @param mixed $campo (requerido)
     * @param int $limite (opcional)
     * @param int $inicio (opcional)
     * @param string $tabla (opcional)
	 * @return array
	 *
	 * El parámetro $campo puede ser de tipo cadena o array asociativo (clave=>valor).
     * En cuanto al último parámetro, $tabla, éste define una tabla distinta a la definida
     * en el inicio de la instancia.
     */
    public function obtener_lista($campo, $limite = 0, $inicio = 0, $tabla = false)
    {
        $tabla = $tabla ? $tabla : $this->tabla;
		
        if (is_array($campo))
        {
            $clave = key($campo);
            $valor = $campo[$clave];
            $seleccion = "`{$tabla}`.`{$clave}`,`{$tabla}`.`{$valor}`";
        } else
            $seleccion = "`{$tabla}`.`{$campo}`";
			
        $this->obtener_info_tabla($this->tabla);
        $union = $this->_preparar_union();
        $desde = $this->_preparar_desde();
        $condicion = $this->_preparar_condicion();
        $grupo = $this->_preparar_grupo();
        $orden = $this->_preparar_orden();
        $limite = $this->_preparar_limite($limite, $inicio);
        $salida = array();
        $this->se_establecio = true;
		
        $resultado = $this->conexion->query("SELECT {$this->diferente}{$seleccion} FROM {$desde} {$union} {$condicion} {$grupo} {$orden} {$limite}",MYSQLI_USE_RESULT);
		
        if ($this->conexion->error)
            $this->error($this->conexion->error);
			
        while ($registro = $resultado->fetch_assoc())
        {
            if (is_array($campo))
                $salida[$registro[$clave]] = $registro[$valor];
            else
                $salida[] = $registro[$campo];
        }
        $resultado->free();
        return $salida;
    }
	
	/** 
     * Devuelve un campo (o columna) y 1 registro (o fila)
	 * Se usa para buscar un único valor.
	 *
     * @access public
	 * @method string obtener_un_campo( string $campo [, string $tabla] )
     * @param string $campo (requerido)
     * @param string $tabla (opcional). Define una tabla distinta a la definida en la instancia.
	 * @return string
     */ 
    public function obtener_un_campo($campo, $tabla = false)
    {
        $tabla = $tabla ? $tabla : $this->tabla;
        $this->obtener_info_tabla($this->tabla);
        $union = $this->_preparar_union();
        $desde = $this->_preparar_desde();
        $condicion = $this->_preparar_condicion();
        $grupo = $this->_preparar_grupo();
        $orden = $this->_preparar_orden();
        $this->se_establecio = true;
		$salida = array();
		
        $resultado = $this->conexion->query("SELECT `{$tabla}`.`{$campo}` FROM {$desde} {$union} {$condicion} {$grupo} {$orden} LIMIT 1",
            MYSQLI_USE_RESULT);
			
        if ($this->conexion->error)
            $this->error($this->conexion->error);
			
        $salida = $resultado->fetch_assoc();
        $resultado->free();
		
        if ($salida && isset($salida[$campo]))
            return $salida[$campo];
        else
            return '';
    }
	
	/** 
     * Elimina registros de la tabla. Puede definir filas específicas,
	 * utilizando el método condicion(). Devuelve el número de registros
	 * afectados.
	 *  
     * @access public
	 * @method int eliminar( [int $limite] [, int $inicio] )
     * @param int $limite (opcional)
     * @param int $inicio (opcional)
	 * @return int
	 *
	 * Ejemplo de uso:	$usuarios = basedatos::tabla('usuarios');
						$usuarios->condicion('id', 55);
						$usuarios->eliminar();
     */ 
    public function eliminar($limite = 0, $inicio = 0)
    {
        $this->obtener_info_tabla($this->tabla);
        $union = $this->_preparar_union();
        $desde = $this->_preparar_desde();
        $condicion = $this->_preparar_condicion();
        $orden = $this->_preparar_orden();
        $limite = $this->_preparar_limite($limite, $inicio);
        $this->se_establecio = true;
        $this->conexion->query("DELETE FROM {$desde} {$union} {$condicion} {$orden} {$limite}");
		
        if ($this->conexion->error)
		{
			if ( $this->transacciones )
				$this->error_trans[] = $this->conexion->error;
			else
				$this->error($this->conexion->error);
				
			return 0;
		} else {
			return $this->conexion->affected_rows; // Devuelve la cantidad de filas afectadas
		}        
    }
	
	/** 
     * Método que como su nombre lo indica nos permite insertar nuevos registros.
	 * Nos devuelve el número de registros afectados.
	 *  
	 * @access public
	 * @method int insertar( array $matriz [, string $tabla] )
     * @param array $matriz (requerido). Define los campos a insertar
     * @param string $tabla (opcional). Define una tabla distinta a la instancia
	 * @return int
	 *	
	 *	Ejemplo:	$usuarios = basedatos::tabla('usuarios');
	 *				$nuevos_usuarios = array('nombre'=>'Manuel','email'=>'manuel@ejemplo.com');
	 *				$usuarios->insertar($nuevos_usuarios);
	 *
	 *	O podemos hacer una insercion por lotes de un modo similar:
	 *
	 *	Ejemplo:    $usuarios = basedatos::tabla('usuarios');
	 *				$nuevos_usuarios = array(
	 *					array('nombre'=>'Miguel','email'=>'miguel@ejemplo.com');
	 *					array('nombre'=>'Jorge','email'=>'jorge@ejemplo.com');
	 *					array('nombre'=>'Julian','email'=>'julian@ejemplo.com');
	 *				$usuarios->insertar($nuevos_usuarios);
	 *  
     */
    public function insertar($matriz = array(), $tabla = false)
    {
        if (is_array($matriz))
        {
            $tabla = $tabla ? $tabla : $this->tabla;
            $valores_de_campo = array();
            $this->se_establecio = true;
			
            if (is_array($matriz[0]))
            {
                $nombres_de_campos = array_keys($matriz[0]);
                foreach ($matriz as $item)
                {
                    $valores_de_campo[] = array_values($item);
                }
            } else
            {
                $nombres_de_campos = array_keys($item);
                $valores_de_campo[0] = array_values($matriz);
            }
            foreach ($valores_de_campo as $clave => $valores)
            {
				// implode = convierte un vector en una cadena separados por un caracter definido
                $valores_de_campo[$clave] = '(\'' . implode('\',\'', $valores) . '\')';
            }
            $this->conexion->query("INSERT INTO `{$tabla}` (`" . implode('`,`', $nombres_de_campos) . "`) VALUES " . implode(',', $valores_de_campo));
			
            if ($this->conexion->error)
			{
				if ( $this->transacciones )
					$this->error_trans[] = $this->conexion->error;
				else
					$this->error($this->conexion->error);
					
				return 0;
			} else {
				return $this->conexion->affected_rows;
			}
        }
    }
	
	/**
	*	Obtiene la Identificación del último elemento insertado
	*
	*	@access public
	*	@method	int id_ultima_insercion( void )
	*	@return int
	*/
	public function id_ultima_insercion()
    {
        return $this->conexion->insert_id;
    }
	
	/** 
     * Método para actualizar registros. Puede filtrar registros,
	 * utilizando el método condicion(). Devuelve el número de registros
	 * afectados.
	 *
     * @access public
	 * @method	int actualizar( array $matriz [, int $limite] [, int $inicio] [, string $tabla] )
     * @param array $matriz (requerido). Define los valores a actualizar
     * @param int $limite (opcional)
     * @param int $inicio (opcional)
     * @param string $tabla (opcional)
	 * @return int
     * 
     * Ejemplo de uso:     $usuarios = basedatos::tabla('usuarios');
     *                     $usuarios->condicion('creado <', '2013-01-24');
     *                     $info = array('publicado'=> 0);
     *                     $usuarios->actualizar($info);
     */
    public function actualizar($matriz = array(), $limite = 0, $inicio = 0, $tabla = false)
    {
        if (is_array($matriz))
        {
            $tabla = $tabla ? $tabla : $this->tabla;
            $condicion = $this->_preparar_condicion();
            $orden = $this->_preparar_orden();
            $limite = $this->_preparar_limite($limite, $inicio);
            $valores_por_campo = array();
            $this->se_establecio = true;
			
            foreach ($matriz as $clave => $item)
            {
                if (is_array($item))
                {
                    foreach ($item as $subclave => $subitem)
                    {
                        $subitem = $this->escapar_caracteres_especiales($subitem);
                        $valores_por_campo[$clave] = "`{$tabla}`.`{$subclave}` = '{$subitem}'";
                    }
                } else
                {
                    $item = $this->escapar_caracteres_especiales($item);
                    $valores_por_campo[0][] = "`{$tabla}`.`{$clave}` = '{$item}'";
                }
            }
            foreach ($valores_por_campo as $valores)
            {
                $this->conexion->query("UPDATE `{$tabla}` SET " . implode(',', $valores) . " {$condicion} {$orden} {$limite}");
                
				if ($this->conexion->error)
				{
					if ( $this->transacciones )
						$this->error_trans[] = $this->conexion->error;
					else
						$this->error($this->conexion->error);
				}
            }
            return $this->conexion->affected_rows;
        }
    }
	
	/** 
     *  Función para actualizar registros.
	 *  Esta función se utilizará luego de realizar
	 *  una consulta usando basedatos::tabla('nombre_tabla').
	 *  
	 *	@name actualizar_fk
	 *	@var $matriz,$limite,$inicio,$tabla
     *	@access public
	 *  @return (int)
     */
    public function actualizar_fk($nombre_clave_izq = '', $clave_izquierda = '', $nombre_clave_der = '', $clave_derecha = '', $tabla = false, $campos = array())
    {
		// Si se definieron los valores en cada variable
        if ($nombre_clave_izq && $clave_izquierda && $nombre_clave_der && $clave_derecha)
        {
            $tabla = $tabla ? $tabla : $this->tabla;
            $new_keys = array();
			
            if (is_array($clave_derecha))
                $new_keys = array_unique($clave_derecha);
            else
                $new_keys = array_unique(explode(',', str_replace(' ', '', $clave_derecha)));
				
            $clave_izquierda = $this->escapar_caracteres_especiales($clave_izquierda);
			
            foreach ($clave_derecha as $clave => $valor_derecho)
            {
                $valor_derecho = $this->escapar_caracteres_especiales($valor_derecho);
                $clave_derecha[$clave] = "('{$clave_izquierda }','{$valor_derecho}')";
            }
			
            $this->conexion->query("DELETE FROM `{$tabla}` WHERE `{$nombre_clave_izq}` = '{$clave_izquierda}' ");
			
            if ($this->conexion->error)
			{
				if ( $this->transacciones )
					$this->error_trans[] = $this->conexion->error;
				else
					$this->error($this->conexion->error);
			}
				
            $this->conexion->query("INSERT INTO `{$tabla}` (`{$nombre_clave_izq}`,`{$nombre_clave_der}`) VALUES " . implode(',', $clave_derecha));
            if ($this->conexion->error)
			{
				if ( $this->transacciones )
					$this->error_trans[] = $this->conexion->error;
				else
					$this->error($this->conexion->error);
			}
            return $this->conexion->affected_rows;
        }
    }
	
    /**
     * Agrega registros o los actualiza si estos existen.
     * Devuelve el número de registros afectados.
     * 
     * @access public
	 * @method	int establecer( array $matriz [, string $tabla] )
     * @param array $matriz (requerido)
     * @param string $tabla (opcional)
	 * @return int
     * 
     * Ejemplo:     $usuarios = basedatos::tabla('usuarios');
	 *				$info = array(
	 *					array('id'=>7,'nombre'=>'Miguel','email'=>'miguel@ejemplo.com');
	 *					array('nombre'=>'Jorge','email'=>'jorge@ejemplo.com');
	 *					array('nombre'=>'Julian','email'=>'julian@ejemplo.com');
	 *				$usuarios->establecer($info);
     * 
     * Para $info[0] sus valores serán actualizados, porque el id declarado existe y es clave.
     * En cambio al no definirse un id o un campo clave en $info[1] y $info[2] estos serán insertados.
     */
    public function establecer($matriz = array(), $tabla = false)
    {
        if (is_array($matriz))
        {
            $tabla = $tabla ? $tabla : $this->tabla;
            $valores_de_campo = array();
            foreach ($matriz as $clave => $item)
            {
                if (is_array($item))
                {
                    foreach ($item as $subclave => $subitem)
                    {
                        $subitem = $this->escapar_caracteres_especiales($subitem);
                        $valores_de_campo[$clave]['ins_key'][] = "`{$tabla}`.`{$subclave}`";
                        $valores_de_campo[$clave]['ins_val'][] = "'{$subitem}'";
                        //if (in_array($subclave, $this->clave_primaria))
                        //    continue;
                        $valores_de_campo[$clave]['actualizar'][] = "`{$tabla}`.`{$subclave}` = '{$subitem}'";
                    }
                } else
                {
                    $item = $this->escapar_caracteres_especiales($item);

                    $valores_de_campo[0]['ins_key'][] = "`{$tabla}`.`{$clave}`";
                    $valores_de_campo[0]['ins_val'][] = "'{$item}'";
                    //if (in_array($clave, $this->clave_primaria))
                    //    continue;
                    $valores_de_campo[0]['actualizar'][] = "`{$tabla}`.`{$clave}` = '{$item}'";
                }
            }
            foreach ($valores_de_campo as $valores)
            {
                //$this->imprimir_matriz($valores);
                $this->conexion->query("INSERT INTO `{$tabla}` (" . implode(',', $valores['ins_key']) . ") VALUES (" . implode(',', $valores['ins_val']) .
                    ") 
                    ON DUPLICATE KEY UPDATE " . implode(',', $valores['actualizar']));
                
				if ($this->conexion->error)
				{
					if ( $this->transacciones )
						$this->error_trans[] = $this->conexion->error;
					else
						$this->error($this->conexion->error);
				}
            }
            return $this->conexion->affected_rows;
        }
    }
    
    /**
     * Define los campos que se levantarán, o no (si se estableció $inverso = true) de la tabla.
     * 
     * @access public
     * @method object campos( mixed $campos [, bool $inverso] [, string $tabla] )
     * @return object
     * 
     * @param mixed $campos (requerido). Admite tanto un string, como un array.
     * @param bool $inverso (opcional). Es de tipo booleano y su valor por defecto es false.
     * @param string $tabla (opcional). Se utiliza para definir una tabla distinta a la instancia reciente.
     * 
     * Ejemplo de uso:  $usuarios = basedatos::tabla('usuarios');
	 *				    $usuarios->campos('nombre','email'); // Extrae solamente los campos 'nombre' e 'email'
	 *				    $detalles = $usuarios->obtener_matriz();
     */
    public function campos($campos = '', $inverso = false, $tabla = false)
    {
        if ($campos)
        {
            if ($this->se_establecio && $this->reinicio_autom)
                $this->reinicializar();
            $tabla = $tabla ? $tabla : $this->tabla;
            if (!isset($this->campos[$tabla]))
                $this->campos[$tabla] = array();
            if (is_array($campos))
                $this->campos[$tabla] = array_unique(array_merge((array )$this->campos[$tabla], $campos));
            else
                $this->campos[$tabla] = array_unique(array_merge((array )$this->campos[$tabla], explode(',', str_replace(' ', '', $campos))));
            $this->campos_invertidos[$tabla] = $inverso;
        }
        return $this;
    }
    
    /**
     * Establece los alias de los campos para luego devolverse como tales.
     * 
     * @access public
     * @method object alias(mixed $campo, string $alias [, string $tabla] )
     * @return object
     * @param mixed $campo (requerido). Puede ser de tipo string definiendo un campo o tipo matriz.
     * @param string $alias (requerido). Nuevo nombre del campo (para la respuesta).
     * @param string $tabla (opcional). Define una tabla distinta a la instancia reciente.
     * 
     * El parámetro campo, cuando es del tipo matriz, debe tener la estructura:
     * campo=>alias.
     * 
     * Ejemplo de uso:  $usuarios = basedatos::tabla('usuarios');
	 *				    $usuarios->alias('id','id_usuario'); // Muestra el resultado del id como id_usuario
	 *				    $detalles = $usuarios->obtener_matriz();
     */
    public function alias($campo = '', $alias = '', $tabla = false)
    {
        if (($campo && $alias) or (is_array($campo)))
        {
            $tabla = $tabla ? $tabla : $this->tabla;
            if (!isset($this->alias[$tabla]))
                $this->alias[$tabla] = array();
            if (is_array($campo))
            {
                $this->alias[$tabla] = array_merge((array )$this->alias[$tabla], $campo);
            } else
            {
                $this->alias[$tabla] = array_merge((array )$this->alias[$tabla], array($campo => $alias));
            }
        }
        return $this;
    }
    
    /**
     * Define una condición (en comando SQL hablamos del uso de WHERE).
     * Sirve para los métodos:
     * obtener_matriz(), obtener_un_registro(), obtener_lista(), obtener_un_campo(),
     * eliminar(), actualizar(), total(), entre otros.
     * 
     * @access public
     * @method object condicion( mixed $campo [, string $valor] [, string $tabla] [, bool $sin_comillas] [, string $unir_con] [, string $operador] )
     * @param mixed $campo (requerido). Puede ser del tipo string o array (campo=>valor)
     * @param string $valor (opcional). Establece la condición
     * @param string $tabla (opcional). Se usa cuando se requiera establecer una tabla distinta a la usada en la instancia.
     * @param bool $sin_comillas (opcional). Previene del uso de comillas.
     * @param string $unir_con (opcional). Operador de unión
     * @param string $operador (opcional). Operador de uso para la condición
     * @return object or instance
     * 
     * Ejemplo de uso:  $usuarios = basedatos::tabla('usuarios');
	 *				    $usuarios->condicion('activos',1); // Muestra el resultado del id como id_usuario
	 *				    $detalles = $usuarios->obtener_matriz();
     */
    public function condicion($campo = '', $valor = '', $tabla = false, $sin_comillas = false, $unir_con = 'AND', $operador = false)
    {
        if ($campo)
        {
            if ($this->se_establecio && $this->reinicio_autom)
                $this->reinicializar();
				
            $tabla = $tabla ? $tabla : $this->tabla;
			
            if (is_array($campo))
            {
                foreach ($campo as $clave => $valor)
                {
                    $this->sql_condicion[] = array(
                        'tabla' => $tabla,
                        'campo' => $clave,
                        'valor' => $valor,
                        'unir_con' => $unir_con,
                        'sin_comillas' => $sin_comillas,
                        'operador' => $operador);
                }
            } else
            {
                $this->sql_condicion[] = array(
                    'tabla' => $tabla,
                    'campo' => $campo,
                    'valor' => $valor,
                    'unir_con' => $unir_con,
                    'sin_comillas' => $sin_comillas,
                    'operador' => $operador);
            }
        }
        return $this;
    }
	
	/**
     * Usado para añadir otra condición
     * 
     * @access public
     * @method object y_condicion( string $campo [, string $valor] [, string $tabla] [, bool $sin_comillas] )
     * @return object
     * 
     * Ejemplo de aplicación:
     * $usuarios = basedatos::tabla('usuarios');
	 * $usuarios->condicion('activos',1)->y_condicion('sexo !=','hombre');
	 * $detalles = $usuarios->obtener_matriz();
     */
    public function y_condicion($campo = '', $valor = '', $tabla = false, $sin_comillas = false)
    {
        return $this->condicion($campo, $valor, $tabla, $sin_comillas, 'AND');
    }
    /**
     * Usado para establecer una condición opcional.
     * 
     * @access public
     * @method object o_condicion( string $campo [, string $valor] [, string $tabla] [, bool $sin_comillas] )
     * @return object
     * 
     * Ejemplo de uso: $usuarios = basedatos::tabla('usuarios');
	 *                 $usuarios->condicion('activos',0)->o_condicion('anulados',1);
	 *                 $detalles = $usuarios->obtener_matriz();    
     */
    public function o_condicion($campo = '', $valor = '', $tabla = false, $sin_comillas = false)
    {
        return $this->condicion($campo, $valor, $tabla, $sin_comillas, 'OR');
    }
    /**
     * Usado para obtener resultados similares (o aplicados en una consulta SQL como LIKE).
     * Está basado en el método condicion().
     * 
     * @access public
     * @method object similar( string $campo [, string $valor] [, string $patron] [, string $tabla] [, string $unir_con] )
     * @param string $campo (requerido). Establece el campo en el que se encuentra nuestra información a buscar.
     * @param string $valor (requerido). Valor a buscar
     * @param string $patron (opcional). Define los caracteres a englobar la expresión para buscar.
     * @return object
     * 
     * Ejemplo de uso: $usuarios = basedatos::tabla('usuarios');
	 *                 $usuarios->similar('nombre','mat'); // puede devolver: matias, mateo, hernán matias, etc.
	 *                 $detalles = $usuarios->obtener_matriz();
     */
    public function similar($campo = '', $valor = '', $patron = array('%', '%'), $tabla = false, $unir_con = 'AND')
    {
        return $this->condicion($campo, $patron[0] . $valor . $patron[1], $tabla, false, $unir_con, 'LIKE');
    }
    /**
     * Añade otra similitud posible para aproximar más la búsqueda
     * 
     * @access public
     * @method object y_similar( string $campo [, string $valor] [, string $patron] [, string $tabla] )
     * @return object
     * 
     * Ejemplo de uso: $usuarios = basedatos::tabla('usuarios');
	 *                 $usuarios->similar('nombre','mat')->y_similar('apellido','amen');
	 *                 $detalles = $usuarios->obtener_matriz();
     */
    public function y_similar($campo = '', $valor = '', $patron = array('%', '%'), $tabla = false)
    {
        return $this->condicion($campo, $patron[0] . $valor . $patron[1], $tabla, false, 'AND', 'LIKE');
    }
    /**
     * Añade otra opción de similitud posible
     * 
     * @access public
     * @method object o_similar( string $campo [, string $valor] [, string $patron] [, string $tabla] )
     * @return object
     * 
     * Ejemplo de uso: $usuarios = basedatos::tabla('usuarios');
	 *                 $usuarios->similar('nombre','mat')->o_similar('nombre','luc');
	 *                 $detalles = $usuarios->obtener_matriz();
     */
    public function o_similar($campo = '', $valor = '', $patron = array('%', '%'), $tabla = false)
    {
        return $this->condicion($campo, $patron[0] . $valor . $patron[1], $tabla, false, 'OR', 'LIKE');
    }
    /**
     * Añade una similitud que se debe despreciar
     * 
     * @access public
     * @method object no_similar( string $campo [, string $valor] [, string $patron] [, string $tabla] [, string $unir_con] )
     * @return object
     * 
     * Ejemplo de uso: $usuarios = basedatos::tabla('usuarios');
	 *                 $usuarios->similar('nombre','mat')->no_similar('nombre','matias');
	 *                 $detalles = $usuarios->obtener_matriz();
     */
    public function no_similar($campo = '', $valor = '', $patron = array('%', '%'), $tabla = false, $unir_con = 'AND')
    {
        return $this->condicion($campo, $patron[0] . $valor . $patron[1], $tabla, false, $unir_con, 'NOT LIKE');
    }
    /**
     * Añade 'otra' similitud que se debe despreciar
     * 
     * @access public
     * @method object y_no_similar( string $campo [, string $valor] [, string $patron] [, string $tabla] )
     * @return object
     * 
     * Ejemplo de uso: $usuarios = basedatos::tabla('usuarios');
	 *                 $usuarios->similar('nombre','mat')->no_similar('nombre','matias')->y_no_similar('nombre','mateo');
	 *                 $detalles = $usuarios->obtener_matriz();
     */
    public function y_no_similar($campo = '', $valor = '', $patron = array('%', '%'), $tabla = false)
    {
        return $this->condicion($campo, $patron[0] . $valor . $patron[1], $tabla, false, 'AND', 'NOT LIKE');
    }
    /**
     * Añade 'otra alternativa' de similitud que se debe despreciar
     * 
     * @access public
     * @method object o_no_similar( string $campo [, string $valor] [, string $patron] [, string $tabla] )
     * @return object
     * 
     * Ejemplo de uso: $usuarios = basedatos::tabla('usuarios');
	 *                 $usuarios->similar('nombre','mat')->no_similar('nombre','matias')->y_no_similar('nombre','mateo');
	 *                 $detalles = $usuarios->obtener_matriz();
     */
    public function o_no_similar($campo = '', $valor = '', $patron = array('%', '%'), $tabla = false)
    {
        return $this->condicion($campo, $patron[0] . $valor . $patron[1], $tabla, false, 'OR', 'NOT LIKE');
    }
    public function en($campo = '', $valors = '', $tabla = false)
    {
        if (!is_array($valores))
        {
            $valores = explode(',', str_replace(' ', '', $valores));
        }
        return $this->condicion($campo, $valores, $tabla, false, 'AND', 'IN');
    }
    public function no_en($campo = '', $valores = '', $tabla = false)
    {
        if (!is_array($valores))
        {
            $valores = explode(',', str_replace(' ', '', $valores));
        }
        return $this->condicion($campo, $valores, $tabla, false, 'AND', 'NOT IN');
    }
    public function y_en($campo = '', $valores = '', $tabla = false)
    {
        if (!is_array($valores))
        {
            $valores = explode(',', str_replace(' ', '', $valores));
        }
        return $this->condicion($campo, $valores, $tabla, false, 'AND', 'IN');
    }
    public function y_no_en($campo = '', $valores = '', $tabla = false)
    {
        if (!is_array($valores))
        {
            $valores = explode(',', str_replace(' ', '', $valores));
        }
        return $this->condicion($campo, $valores, $tabla, false, 'AND', 'NOT IN');
    }
    public function o_en($campo = '', $valores = '', $tabla = false)
    {
        if (!is_array($valores))
        {
            $valores = explode(',', str_replace(' ', '', $valores));
        }
        return $this->condicion($campo, $valores, $tabla, false, 'OR', 'IN');
    }
    public function o_no_en($campo = '', $valores = '', $tabla = false)
    {
        if (!is_array($valores))
        {
            $valores = explode(',', str_replace(' ', '', $valores));
        }
        return $this->condicion($campo, $valores, $tabla, false, 'OR', 'NOT IN');
    }

    public function texto_completo($campos, $expresion, $modo = 'natural', $tabla = false)
    {
        if ($campos && $tabla)
        {
            if ($this->se_establecio && $this->reinicio_autom)
                $this->reinicializar();
            $tabla = $tabla ? $tabla : $this->tabla;
            if (is_array($campos))
                $campos = array_unique($campos);
            else
                $campos = array_unique(explode(',', str_replace(' ', '', $campos)));
            $this->sql_completo = array(
                'campos' => $campos,
                'expresion' => $expresion,
                'tabla' => $tabla,
                'modo' => $modo);
        }
        return $this;
    }
    /**
     * Ordena el resultado
     * 
     * @access public
     * @method object listar_por( string $campo [, string $direccion] [, string $tabla] )
     * @param string $campo (requerido). Establece el campo a ordenar
     * @param string $direccion (opcional). Establece el tipo de orden asc (ascendente) o desc (descendente)
     * @return object
     * 
     * Ejemplo de uso: $noticias = basedatos::tabla('novedades');
	 *                 $noticias->listar_por('titulo');
     *                 $noticias->listar_por('creado','desc');
	 *                 $detalles = $noticias->obtener_matriz();
     */
    public function listar_por($campo = '', $direccion = 'asc', $tabla = false)
    {
        if ($campo)
        {
            if ($this->se_establecio && $this->reinicio_autom)
                $this->reinicializar();
            $tabla = $tabla ? $tabla : $this->tabla;
            $this->orden[] = array(
                'tabla' => $tabla,
                'campo' => $campo,
                'direccion' => ($direccion == 'desc') ? 'desc' : 'asc');
        }
        return $this;
    }
    /**
     * Alias del método listar_por
     * 
     * @access public
     * @return object
     */
    public function ordenar_por($campo = '', $direccion = 'asc', $tabla = false)
    {
        return $this->listar_por($campo, $direccion, $tabla);
    }
    /**
     * Devuelve resultados en forma aleatoria.
     * 
     * @access public
     * @method object aleatorio ( void )
     * @return object
     * 
     * Ejemplo de uso: $noticias = basedatos::tabla('novedades');
	 *                 $noticias->aleatorio();
	 *                 $detalles = $noticias->obtener_matriz(5); // Devuelve 5 noticias, aleatorias
     */
    public function aleatorio()
    {
        if ($this->se_establecio && $this->reinicio_autom)
            $this->reinicializar();
        $this->aleatorio = true;
        return $this;
    }
    
    /**
     * Selecciona múltiples tablas con este método.
     * 
     * @access private
     * @method object join( string $tipo, string $campo, string $tabla_unida, string $campo_combinado [, string $tabla_izquierda] [, string $alias ] [, mixed $campos_derechos] [, bool $inverso] )
     * @param string $tipo (requerido). Se estable el tipo de unión, ya sea LEFT, RIGHT, INNER, etc.
     * @param string $campo (requerido). Campo de la tabla principal.
     * @param string $tabla_unida (requerido). Tabla unida o a ligar.
     * @param string $campo_combinado (requerido). Campo de relación, de la tabla unida.
     * @param string $tabla_izquierda (opcional). Tabla principal. Se debe definir si se requiere una tabla diferente a la establecida en la instancia.
     * @param string $alias (opcional). Use este parámetro para definir otro nombre a la tabla si se requiere unir la misma tabla.
     * @param mixed $campos_derechos (opcional). Campos seleccionados de la tabla unida (vea el método campos()).
     * @param bool $inverso (opcional). Es de tipo booleano y su valor por defecto es false.
     * @return object
     * 
     */
    private function unir($tipo = 'LEFT', $campo = '', $tabla_unida = '', $campo_combinado = '', $tabla_izquierda = false, $alias = false, $campos_derechos = false,
        $inverso = false)
    {
        if ($tipo && $campo && $campo_combinado && $tabla_unida)
        {
            if ($this->se_establecio && $this->reinicio_autom)
                $this->reinicializar();
            $local_tabla_izq = $tabla_izquierda ? $tabla_izquierda : $this->tabla;
            $alias = $alias ? $alias : $tabla_unida;
            $condicion = "`{$local_tabla_izq}`." . $this->_preparar_campo($campo) . "`{$alias}`.`{$campo_combinado}`";
            $this->union[] = array(
                'tabla_izquierda' => $local_tabla_izq,
                'tabla_derecha' => $tabla_unida,
                'condicion' => $condicion,
                'alias' => $alias,
                'tipo' => $tipo);
            if ($campos_derechos)
            {
                $this->campos($campos_derechos, $inverso, $alias);
            }
        }
        return $this;
    }
    /**
     * El método refiere a un LEFT JOIN en SQL.
     * 
     * @access public
     * @method object union_izquierda( string $campo, string $tabla_a_unir, string $campo_combinado [, string $tabla_izquierda] [, string $alias ] [, mixed $campos_derechos] [, bool $inverso] )
     * @return object
     * 
     * Ejemplo de uso: $noticias = basedatos::tabla('novedades');
	 *                 $noticias->union_izquierda('cat_id','categorias','id'); // Selecciona las novedades con sus categorías
	 *                 $detalles = $noticias->obtener_matriz();
     * 
     */
    public function union_izquierda($campo = '', $tabla_a_unir = '', $campo_combinado = '', $tabla_izquierda = false, $alias = false, $campos_derechos = false,
        $inverso = false)
    {
        return $this->unir($tipo = 'LEFT', $campo, $tabla_a_unir, $campo_combinado, $tabla_izquierda, $alias = false, $campos_derechos, $inverso);
    }
    public function union_derecha($campo = '', $tabla_a_unir = '', $campo_combinado = '', $tabla_izquierda = false, $alias = false, $campos_derechos = false,
        $inverso = false)
    {
        return $this->unir($tipo = 'RIGHT', $campo, $tabla_a_unir, $campo_combinado, $tabla_izquierda, $alias = false, $campos_derechos, $inverso);
    }
    public function combinar($campo = '', $tabla_a_unir = '', $campo_combinado = '', $tabla_izquierda = false, $alias = false, $campos_derechos = false,
        $inverso = false)
    {
        return $this->unir($tipo = 'INNER', $campo, $tabla_a_unir, $campo_combinado, $tabla_izquierda, $alias = false, $campos_derechos, $inverso);
    }
    public function agrupar_por($campo = '', $tabla = false)
    {
        if ($campo)
        {
            if ($this->se_establecio && $this->reinicio_autom)
                $this->reinicializar();
            $tabla = $tabla ? $tabla : $this->tabla;
            $this->grupo[] = array('tabla' => $tabla, 'campo' => $campo);
        }
        return $this;
    }

    public function consulta_simple($consulta = '')
    {
        $this->resultado = $this->conexion->query($consulta, MYSQLI_USE_RESULT);
        if ($this->conexion->error)
            $this->error($this->conexion->error);
        return $this;
    }
    
	
	
    public function devolver_matriz()
    {
        $salida = array();
        if ($this->resultado)
        {
            while ($fila = $this->resultado->fetch_assoc())
            {
                $salida[] = $fila;
            }
            $this->resultado->free();
        }
        return $salida;
    }
    public function registro()
    {
        $obj = $this->resultado->fetch_assoc();
        $this->resultado->free();
        return $obj;
    }
    public function escapar($valor, $limpiar = false)
    {
        if (is_int($valor))
            return (int)$valor;
        if ($limpiar)
            return $this->magic_quotes_gpc ? $valor : $this->conexion->real_escape_string($valor);
			
        return '\'' . ($this->magic_quotes_gpc ? $valor : $this->conexion->real_escape_string($valor)) . '\'';
    }
	
    public function sumar($campo = '', $alias = false, $tabla = false)
    {
        $tabla = $tabla ? $tabla : $this->tabla;
        if ($alias)
        {
            if ($this->se_establecio && $this->reinicio_autom)
                $this->reinicializar();
				
            $this->sql_campos[] = "SUM(`{$tabla}`.`{$campo}`) AS `{$alias}`";
			
            return $this;
        } else
        {
            $union = $this->_preparar_union();
            $condicion = $this->_preparar_condicion();
            $grupo = $this->_preparar_grupo();
            $this->se_establecio = true;
            $registro = $this->query("SELECT SUM(`{$tabla}`.`{$campo}`) AS `{$campo}` FROM `{$tabla}` {$union} {$condicion} {$grupo}")->registro();
            return $registro[$campo];
        }
    }
	
    public function valor_maximo($campo = '', $alias = false, $tabla = false)
    {
        $tabla = $tabla ? $tabla : $this->tabla;
        if ($alias)
        {
            if ($this->se_establecio && $this->reinicio_autom)
                $this->reinicializar();
            $this->sql_campos[] = "MAX(`{$tabla}`.`{$campo}`) AS `{$alias}`";
            return $this;
        } else
        {
            $union = $this->_preparar_union();
            $condicion = $this->_preparar_condicion();
            $grupo = $this->_preparar_grupo();
            $this->se_establecio = true;
            $registro = $this->query("SELECT MAX(`{$tabla}`.`{$campo}`) AS `{$campo}` FROM `{$tabla}` {$union} {$condicion} {$grupo}")->registro();
            return $registro[$campo];
        }
    }
	
    public function valor_minimo($campo = '', $alias = false, $tabla = false)
    {
        $tabla = $tabla ? $tabla : $this->tabla;
        if ($alias)
        {
            if ($this->se_establecio && $this->reinicio_autom)
                $this->reinicializar();
            $this->sql_campos[] = "MIN(`{$tabla}`.`{$campo}`) AS `{$alias}`";
            return $this;
        } else
        {
            $union = $this->_preparar_union();
            $condicion = $this->_preparar_condicion();
            $grupo = $this->_preparar_grupo();
            $this->se_establecio = true;
            $registro = $this->query("SELECT MIN(`{$tabla}`.`{$campo}`) AS `{$campo}` FROM `{$tabla}` {$union} {$condicion} {$grupo}")->registro();
            return $registro[$campo];
        }
    }
	
    public function concatenar($campos = '', $alias = '', $separador = ',', $tabla = false)
    {
        if ($campos && $alias)
        {
            if ($this->se_establecio && $this->reinicio_autom)
                $this->reinicializar();
            $tabla = $tabla ? $tabla : $this->tabla;
            if (!is_array($campos))
                $concat_str = explode(',', str_replace(' ', '', $campos));
            foreach ($campos as $campo)
                $concat_str[] = "`{$tabla}`.`{$campo}`";
            $concat_str = implode(',', $campos);
            $this->sql_campos[] = "CONCAT_WS(`{$tabla}`.`{$campo}`) AS `{$alias}`";
        }
        return $this;
    }
	// Con la función 'agrupar_concatenado' todas las categorías que se ven agrupadas por el GROUP BY,
	// serán concatenadas en un campo, con una coma como separador (por defecto).
    public function agrupar_concatenado($campo = '', $alias = '', $separador = ',', $tabla = false, $diferente = true, $ordenar_por = false,
        $direct = 'ASC')
    {
        if ($campo)
        {
            if ($this->se_establecio && $this->reinicio_autom)
                $this->reinicializar();
            $alias = $alias ? $alias : $campo;
            $tabla = $tabla ? $tabla : $this->tabla;
            $separador = $this->escapar_caracteres_especiales($separador);
            $orden = $ordenar_por ? "ORDER BY `{$ordenar_por}` {$direct}" : '';
            $this->sql_campos[] = 'GROUP_CONCAT(' . ($diferente ? 'DISTINCT ' : '') . "`{$tabla}`.`{$campo}` {$orden} SEPARATOR '{$separador}') AS `{$alias}`";
        }
        return $this;
    }
	
	// La función es_unico devuelve verdadero o falso si el valor en una tabla es único.
    public function es_unico($campo = '', $valor = '', $tabla = false)
    {
        if ($campo)
        {
            $tabla = $tabla ? $tabla : $this->tabla;
            $valor = $this->escapar_caracteres_especiales($valor);
            $resultado = $this->conexion->query("SELECT COUNT({$this->diferente}`{$tabla}`.`{$campo}`) AS `count` FROM `{$tabla}` WHERE `{$tabla}`.`{$campo}` = '{$valor}' LIMIT 1");
			
            if ($this->conexion->error)
                $this->error($this->conexion->error);
            else
                return !(bool)$resultado->fetch_object()->count;
        }
    }
	
	// Función que setea las variables de clase
    public function reinicializar()
    {
        $this->sql_campos = array();
        $this->sql_completo = array();
        $this->sql_condicion = array();
        $this->campos = array();
        $this->campos_invertidos = array();
        $this->aleatorio = false;
        $this->grupo = array();
        $this->orden = array();
        $this->union = array();
        $this->se_establecio = false;
        return $this;
    }
	
    
	
    public function incrementar($campo = '', $incremento = 1, $tabla = false)
    {
        if ($campo)
        {
            $incremento = (int)$incremento;
            $tabla = $tabla ? $tabla : $this->tabla;
            $union = $this->_preparar_union();
            $condicion = $this->_preparar_condicion();
            $this->se_establecio = true;
            $this->conexion->query("UPDATE `$tabla` SET `{$campo}` = `{$campo}` + {$incremento} {$union} ");
			
			if ($this->conexion->error)
			{
				if ( $this->transacciones )
					$this->error_trans[] = $this->conexion->error;
				else
					$this->error($this->conexion->error);
			}
        }
    }
	
    public function disminuir($campo = '', $decremento = 1, $tabla = false)
    {
        if ($campo)
        {
            $decremento = (int)$decremento;
            $tabla = $tabla ? $tabla : $this->tabla;
            $union = $this->_preparar_union();
            $condicion = $this->_preparar_condicion();
            $this->se_establecio = true;
            $this->conexion->query("UPDATE `$tabla` SET `{$campo}` = `{$campo}` - {$decremento} {$condicion} ");
			
			if ($this->conexion->error)
			{
				if ( $this->transacciones )
					$this->error_trans[] = $this->conexion->error;
				else
					$this->error($this->conexion->error);
			}
        }
    }
	
    public function diferente()
    {
        if ($this->se_establecio && $this->reinicio_autom)
            $this->reinicializar();
			
        $this->diferente = 'DISTINCT ';
    }

    ###

    private function obtener_info_tabla($tabla, $alias = '')
    {
		if ( $alias == '' )
			$alias = $tabla;
		
		// Consulta que devuelve los campos de la tabla
        $resultado = $this->conexion->query("SHOW COLUMNS FROM `{$tabla}`", MYSQLI_USE_RESULT);
        
		if ($this->conexion->error)
            $this->error($this->conexion->error);
			
        //$this->imprimir_matriz($this->campos);
        while ($registro = $resultado->fetch_object())
        {
            //$this->imprimir_matriz($registro);
            if ($registro->Key == 'UNI')
                $this->clave_unica[$registro->Field] = true;
            if ($registro->Key == 'PRI')
                $this->clave_primaria[$registro->Field] = true;
            if ($registro->Key == 'PRI' && $registro->Extra == 'auto_increment')
            {
                $this->auto_incrementable[$registro->Field] = true;
            }
            //$this->campos["{$tabla}.{$registro->Field}"] = $registro->Field;
            if (!isset($this->campos[$tabla]) or (isset($this->campos[$tabla]) && in_array($registro->Field, $this->campos[$tabla])) or (isset
                ($this->campos_invertidos[$tabla]) && $this->campos_invertidos[$tabla] && !in_array($registro->Field, $this->campos[$tabla])))
                $this->sql_campos["{$alias}.{$registro->Field}"] = "`{$alias}`.`{$registro->Field}`" . (isset($this->alias[$tabla][$registro->Field]) ?
                    " AS `{$this->alias[$tabla][$registro->Field]}`" : '');
        }
    }
	
	// Función que escapa de caracteres especiales
    public function escapar_caracteres_especiales($valor)
    {
        if (is_int($valor))
            return (int)$valor;
			
        if (!$this->magic_quotes_gpc)
            return $this->conexion->real_escape_string($valor);
			
        return $valor;
    }
	
    private function _preparar_seleccion()
    {
        return implode(', ', $this->sql_campos);
    }
	
    private function _preparar_desde()
    {
        return "`{$this->tabla}`";
    }
	
    private function _preparar_union()
    {
        $union = array();
		
        if ($this->union)
        {
            foreach ($this->union as $tabla_union)
            {
                $this->tabla_alias[$tabla_union['tabla_derecha']] = $tabla_union['alias'];
                $this->obtener_info_tabla($tabla_union['tabla_derecha'], $tabla_union['alias']);
                $union[] = "{$tabla_union['tipo']} JOIN `{$tabla_union['tabla_derecha']}` AS `{$tabla_union['alias']}` ON {$tabla_union['condicion']}";
            }
            return implode(' ', $union);
        }
        return '';
    }
	
    private function _preparar_condicion()
    {
        $condicion = '';
        if ($this->sql_completo)
        {
            foreach ($this->sql_completo['campos'] as $clave => $valor)
            {
                $this->sql_completo['campos'][$clave] = "`{$this->sql_completo['tabla']}`.`{$valor}`";
            }
            $condicion .= 'WHERE MATCH (' . implode(',', $this->sql_completo['campos']) . ') AGAINST (\'' . $this->escapar_caracteres_especiales($this->
                sql_completo['expresion']) . '\'';
            switch ($this->sql_completo['modo'])
            {
                case 'booleano':
                    $condicion .= ' IN BOOLEAN MODE';
                    break;
                case 'expansion':
                    $condicion .= ' WITH QUERY EXPANSION';
                    break;
            }
            $condicion .= ')';
        }
        if ($this->sql_condicion)
        {
            foreach ($this->sql_condicion as $k => $w)
            {
                if (isset($this->tabla_alias[$w['tabla']]))
                    $w['tabla'] = $this->tabla_alias[$w['tabla']];
                if ($k > 0 or $condicion != '')
                    $condicion .= $w['unir_con'];
                else
                    $condicion .= 'WHERE';
                if (mb_stripos($w['operador'], 'IN') !== false)
                {
                    foreach ($w['valor'] as $wclave => $wvalor)
                    {
                        $w['valor'][$wclave] = '\'' . $this->escapar_caracteres_especiales($wvalor) . '\'';
                    }
                    $condicion .= " `{$w['tabla']}`.`{$w['campo']}` {$w['operador']} (" . implode(',', $w['valor']) . ') ';
                } else
                {
                    $w['valor'] = $w['sin_comillas'] ? $w['valor'] : '\'' . $w['valor'] . '\'';
                    if ($w['operador'])
                        $condicion .= " `{$w['tabla']}`.`{$w['campo']}` {$w['operador']} {$w['valor']} ";
                    else
                        $condicion .= " `{$w['tabla']}`." . $this->_preparar_campo($w['campo']) . " {$w['valor']} ";
                }
            }
        }

        return $condicion;
    }
    private function _preparar_orden()
    {
        $orden = array();
        if ($this->aleatorio)
        {
            $orden[] = 'RAND()';
        }
        if ($this->orden)
        {
            foreach ($this->orden as $parametro)
            {
                if (isset($this->tabla_alias[$parametro['tabla']]))
                    $parametro['tabla'] = $this->tabla_alias[$parametro['tabla']];
                $orden[] = "`{$parametro['tabla']}`.`{$parametro['campo']}` {$parametro['direccion']}";
            }

        }
        if ($orden)
            return 'ORDER BY ' . implode(', ', $orden);
        return '';
    }
    private function _preparar_grupo()
    {
        if ($this->grupo)
        {
            $grupo = array();
            foreach ($this->grupo as $parametro)
            {
                $grupo[] = "`{$parametro['tabla']}`.`{$parametro['campo']}`";
            }
            return 'GROUP BY ' . implode(', ', $grupo);
        }
        return '';
    }
    private function _preparar_limite($limite, $inicio)
    {
        if ($limite != 0)
        {
            return "LIMIT {$inicio}, {$limite}";
        }
    }

    private function _preparar_campo($campo)
    {
        preg_match_all('/([^<>!=]+)/', $campo, $coincidencias);
        preg_match_all('/([<>!=]+)/', $campo, $coincidencias2);
        return '`' . trim($coincidencias[0][0]) . '`' . ($coincidencias2[0] ? implode('', $coincidencias2[0]) : '=');
    }
    private function error($texto = 'Error!')
    {
        echo ('<div style="padding:15px;color:red;margin:10px;border:1px solid red;border-radius:2px;">' . $texto .
            '</div>');
    }
    public function imprimir_matriz($var)
    {
        echo '<pre>' . print_r($var, 1) . '</pre>';
    }
}