<?php
include "conexion.php";

try {
    $stmt = $conexion->query("SELECT u.id_usuario, u.nombre, u.email, r.nombre_rol, e.descripcion AS estado 
                              FROM usuarios u
                              JOIN rol r ON u.id_rol = r.id_rol
                              JOIN estado e ON u.id_estado = e.id_estado");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($usuarios as $usuario) {
        echo "<tr>
                <td>{$usuario['id_usuario']}</td>
                <td>{$usuario['nombre']}</td>
                <td>{$usuario['email']}</td>
                <td>{$usuario['nombre_rol']}</td>
                <td>{$usuario['estado']}</td>
                <td>
                    <button onclick=\"editarUsuario({$usuario['id_usuario']})\">Editar</button>
                    <button onclick=\"eliminarUsuario({$usuario['id_usuario']})\">Eliminar</button>
                </td>
              </tr>";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

//esto era un borrador xddd, pero los usuarios no podran hacer eso creo, entonces esto es un relleno que quizas debamos borrar
?>
