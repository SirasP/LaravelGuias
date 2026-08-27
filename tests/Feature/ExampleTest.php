<?php

it('sends a visitor without session to the login screen', function () {
    // La raíz no es pública: esta aplicación es interna y todo exige sesión.
    // El test que venía con Laravel esperaba un 200 y llevaba tiempo en rojo.
    $this->get('/')->assertRedirect('/login');
});

// La pantalla de inicio en sí no se puede probar aquí: consulta con
// JSON_UNQUOTE, que MySQL tiene y el SQLite de los tests no. Comprobarla
// exigiría montar la suite contra MySQL, que es otra conversación.
