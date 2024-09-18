<?php
/*
Plugin Name: API DE CONEXIÓN
Description: Proporciona servicios POST y GET para manejar cédulas y otros datos.
Version: 1.0
Author: Christian Cortés
*/


add_action('rest_api_init', function () {
    // Ruta para el servicio GET
    register_rest_route('pruebaapi/v1', '/obtener-datos/', [
        'methods' => 'GET',
        'callback' => 'pruebaapi_obtener_datos',
        'permission_callback' => 'validar_token_api', // Valida el token antes de ejecutar el callback
    ]);

    // Ruta para el servicio POST
    register_rest_route('pruebaapi/v1', '/enviar-datos/', [
        'methods' => 'POST',
        'callback' => 'pruebaapi_enviar_datos',
        'permission_callback' => 'validar_token_api', // Valida el token antes de ejecutar el callback
    ]);
});


function validar_token_api(WP_REST_Request $request) {
    $headers = getallheaders(); // Obtén los encabezados de la solicitud
    $token_enviado = isset($headers['Authorization']) ? $headers['Authorization'] : '';

    // Obtiene el token almacenado
    $token_almacenado = get_option('api_access_token');

    // Verifica si el token coincide
    if ($token_enviado === 'Bearer ' . $token_almacenado) {
        return true; // Permite el acceso
    }

    return false; // Acceso denegado
}


// Función para manejar el servicio GET
function pruebaapi_obtener_datos(WP_REST_Request $request) {
    
    //  $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD);
    // mysqli_select_db($conn, DB_NAME);
    // global $wpdb;
    //$tabla = $wpdb->prefix . 'donaciones_placetopay_pagos';
    // $sql = "SELECT * FROM $tabla";
    // $suscripciones = mysqli_query($conn, $sql);
    global $wpdb;
    $tabla = $wpdb->prefix . 'donaciones_placetopay_suscripciones';
    
    // Consulta para obtener todas las suscripciones
    $suscripciones = $wpdb->get_results("SELECT * FROM $tabla", ARRAY_A);
    
    if (!empty($suscripciones)) {
        return new WP_REST_Response([
            'message' => 'Suscripciones encontradas',
            'suscripciones' => $suscripciones
        ], 200);
    } else {
        return new WP_REST_Response([
            'message' => 'No se encontraron suscripciones'
        ], 404);
    }
}


function pruebaapi_enviar_datos(WP_REST_Request $request) {
    // Obtener los datos enviados en el POST
    $cedula = sanitize_text_field($request->get_param('cedula'));
    $telefono = sanitize_text_field($request->get_param('telefono'));
    $provincia = sanitize_text_field($request->get_param('provincia'));
    $monto = floatval($request->get_param('monto')); // Convertir a decimal
    $token_pos_enviado = sanitize_text_field($request->get_param('token_pos')); // Obtener el token POS del JSON

    // Validar que los campos obligatorios no estén vacíos
    if (empty($cedula) || empty($telefono) || empty($provincia) || $monto === null || empty($token_pos_enviado)) {
        return new WP_REST_Response(['message' => 'Faltan parámetros'], 400);
    }

    // Obtener el token POS almacenado en la base de datos
    $token_pos_almacenado = get_option('api_post_token');

    // Verificar si el token POS coincide
    if ($token_pos_enviado !== $token_pos_almacenado) {
        return new WP_REST_Response(['message' => 'Token POS inválido'], 403);
    }

    global $wpdb;
    $tabla = $wpdb->prefix . 'donaciones_yaganaste_api';

    // Primero, insertamos los datos sin el código único
    $resultado = $wpdb->insert(
        $tabla,
        [
            'cedula' => $cedula,
            'telefono' => $telefono,
            'provincia' => $provincia,
            'monto' => $monto
        ],
        [
            '%s', // Tipo de dato para cedula
            '%s', // Tipo de dato para telefono
            '%s', // Tipo de dato para provincia
            '%f'  // Tipo de dato para monto
        ]
    );

    // Verificamos si la inserción fue exitosa
    if ($resultado !== false) {
        // Obtenemos el ID generado
        $id_generado = $wpdb->insert_id;

        // Calculamos el código único incluyendo el ID generado
        $codigo_unico = md5($id_generado . $cedula . $telefono . $provincia . $monto);

        // Actualizamos la fila con el código único generado
        $wpdb->update(
            $tabla,
            ['codigo_unico' => substr($codigo_unico, 0, 8)], // Tomamos los primeros 8 caracteres
            ['id' => $id_generado], // Condición para actualizar solo la fila recién insertada
            ['%s'], // Tipo de dato para codigo_unico
            ['%d']  // Tipo de dato para id
        );

        return new WP_REST_Response([
            'message' => 'Datos guardados correctamente',
            'codigo_unico' => substr($codigo_unico, 0, 8) // Retornar el código único generado
        ], 200);
    } else {
        return new WP_REST_Response(['message' => 'Error al guardar los datos'], 500);
    }
}
