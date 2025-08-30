<?php

return [
    // Basic file validation messages
    'file_required' => 'Por favor seleccione un archivo CSV.',
    'file_invalid' => 'El archivo debe ser un archivo válido.',
    'file_wrong_type' => 'El archivo debe ser un CSV (.csv o .txt).',
    'file_too_large' => 'El archivo no puede ser mayor a 10MB.',

    // CSV-specific validation messages
    'file_invalid_csv' => 'El archivo CSV no es válido: :error',
    'validation_error' => 'Error al validar el archivo. Por favor, inténtelo de nuevo.',

    // Line limit validation messages
    'free_tier_limit_exceeded' => 'El archivo tiene :lines líneas, pero el plan gratuito está limitado a :limit líneas. Considere actualizar su cuenta o reducir el tamaño del archivo.',
    'custom_limit_exceeded' => 'El archivo tiene :lines líneas, pero su límite actual es de :limit líneas.',
    'anonymous_limit_exceeded' => 'El archivo tiene :lines líneas, pero los usuarios anónimos están limitados a :limit líneas. Regístrese para obtener más funciones.',

    // General limit information
    'current_limit_info' => 'Su límite actual es de :limit líneas por archivo.',
    'free_tier_info' => 'Plan gratuito: limitado a :limit líneas por archivo CSV.',
    'custom_limit_info' => 'Límite personalizado: :limit líneas por archivo',
    'admin_limit_info' => 'Administrador: sin límites de líneas por archivo.',
    'limit_expires' => 'Este límite expira el :date.',

    // Upgrade prompts
    'upgrade_suggestion' => 'Para procesar archivos más grandes, considere actualizar a un plan premium.',
    'contact_admin' => 'Para aumentos temporales de límites, contacte al administrador.',

    // Success messages
    'upload_success' => 'Archivo subido exitosamente. Procesamiento iniciado.',
    'validation_passed' => 'El archivo pasa todas las validaciones (:lines líneas).',
];

