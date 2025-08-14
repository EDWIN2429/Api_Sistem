<?php

/**
 * Archivo de Prueba de Conexión
 * Para verificar que la configuración de BD funciona correctamente
 */

require_once 'app/config.php';
require_once 'app/database.php';

echo "<h1>🧪 Prueba de Conexión a Base de Datos</h1>";

try {
    // Obtener instancia de BD
    $db = getDB();
    echo "<p>✅ Conexión a base de datos exitosa</p>";

    // Verificar si la tabla queries existe
    if ($db->tableExists('queries')) {
        echo "<p>✅ Tabla 'queries' existe</p>";

        // Mostrar estructura de la tabla
        $structure = $db->query("DESCRIBE queries");
        echo "<h3>📋 Estructura de la tabla 'queries':</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Llave</th><th>Default</th><th>Extra</th></tr>";
        foreach ($structure as $field) {
            echo "<tr>";
            echo "<td>{$field['Field']}</td>";
            echo "<td>{$field['Type']}</td>";
            echo "<td>{$field['Null']}</td>";
            echo "<td>{$field['Key']}</td>";
            echo "<td>{$field['Default']}</td>";
            echo "<td>{$field['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Mostrar consultas existentes
        $queries = $db->query("SELECT * FROM queries");
        if (!empty($queries)) {
            echo "<h3>📝 Consultas existentes:</h3>";
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Título</th><th>Consulta SQL</th><th>Creado</th></tr>";
            foreach ($queries as $query) {
                echo "<tr>";
                echo "<td>{$query['id']}</td>";
                echo "<td>{$query['title']}</td>";
                echo "<td style='max-width: 300px; word-wrap: break-word;'>{$query['sql_query']}</td>";
                echo "<td>{$query['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>ℹ️ No hay consultas en la tabla</p>";
        }
    } else {
        echo "<p>⚠️ Tabla 'queries' no existe, creándola...</p>";
        if ($db->createQueriesTable()) {
            echo "<p>✅ Tabla 'queries' creada exitosamente</p>";
        } else {
            echo "<p>❌ Error al crear la tabla 'queries'</p>";
        }
    }

    // Mostrar información de configuración
    echo "<h3>⚙️ Información de Configuración:</h3>";
    echo "<ul>";
    echo "<li><strong>Host:</strong> " . DB_HOST . "</li>";
    echo "<li><strong>Base de Datos:</strong> " . DB_NAME . "</li>";
    echo "<li><strong>Usuario:</strong> " . DB_USER . "</li>";
    echo "<li><strong>Charset:</strong> " . DB_CHARSET . "</li>";
    echo "<li><strong>Token API:</strong> " . substr(API_TOKEN, 0, 10) . "...</li>";
    echo "<li><strong>Usuario Admin:</strong> " . ADMIN_USERNAME . "</li>";
    echo "<li><strong>Entorno:</strong> " . APP_ENV . "</li>";
    echo "</ul>";
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>🔧 Verifica la configuración en config.php</p>";
}

echo "<hr>";
echo "<p><strong>📚 Próximos pasos:</strong></p>";
echo "<ol>";
echo "<li>Verificar que XAMPP esté corriendo</li>";
echo "<li>Crear la base de datos 'api_system' en phpMyAdmin</li>";
echo "<li>Ejecutar el script database_setup.sql</li>";
echo "<li>Probar la conexión con este archivo</li>";
echo "</ol>";
