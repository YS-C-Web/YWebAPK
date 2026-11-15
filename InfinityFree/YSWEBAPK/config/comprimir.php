<?php
// config/comprimir.php

if (!function_exists('imagecreatefromjpeg')) {
    // Si el servidor no tiene la librería GD activa, salimos silenciosamente.
    return;
}

function comprimirImagen($ruta_origen, $ruta_destino, $calidad = 80, $max_ancho = 1200) {
    if (!file_exists($ruta_origen)) return false;

    $info = @getimagesize($ruta_origen);
    if ($info === false) return false;

    list($ancho_original, $alto_original, $tipo) = $info;
    $nuevo_ancho = $ancho_original;
    $nuevo_alto = $alto_original;

    if ($ancho_original > $max_ancho) {
        $nuevo_ancho = $max_ancho;
        $nuevo_alto = intval($alto_original * ($max_ancho / $ancho_original));
    }

    $imagen = null;
    switch ($tipo) {
        case IMAGETYPE_JPEG: $imagen = @imagecreatefromjpeg($ruta_origen); break;
        case IMAGETYPE_PNG:  $imagen = @imagecreatefrompng($ruta_origen); break;
        case IMAGETYPE_WEBP: $imagen = @imagecreatefromwebp($ruta_origen); break;
        case IMAGETYPE_GIF:  $imagen = @imagecreatefromgif($ruta_origen); break;
        default: return false;
    }

    if (!$imagen) return false;

    $imagen_destino = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);
    
    if ($tipo == IMAGETYPE_PNG || $tipo == IMAGETYPE_WEBP) {
        imagealphablending($imagen_destino, false);
        imagesavealpha($imagen_destino, true);
        $transparente = imagecolorallocatealpha($imagen_destino, 255, 255, 255, 127);
        imagefilledrectangle($imagen_destino, 0, 0, $nuevo_ancho, $nuevo_alto, $transparente);
    }

    imagecopyresampled($imagen_destino, $imagen, 0, 0, 0, 0, $nuevo_ancho, $nuevo_alto, $ancho_original, $alto_original);

    $exito = false;
    switch ($tipo) {
        case IMAGETYPE_JPEG: $exito = @imagejpeg($imagen_destino, $ruta_destino, $calidad); break;
        case IMAGETYPE_PNG:  $exito = @imagepng($imagen_destino, $ruta_destino, round(9 - ($calidad / 100 * 9))); break;
        case IMAGETYPE_WEBP: $exito = @imagewebp($imagen_destino, $ruta_destino, $calidad); break;
        case IMAGETYPE_GIF:  $exito = @imagegif($imagen_destino, $ruta_destino); break;
    }

    @imagedestroy($imagen);
    @imagedestroy($imagen_destino);
    return $exito;
}