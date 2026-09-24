<?php
// Sem o mod_rewrite do Apache, quem abrir a raiz do projeto é levado ao front controller.
// (Com o mod_rewrite ligado — padrão do XAMPP — este arquivo nem é executado:
// o .htaccess já desvia tudo para public/.)
header('Location: public/', true, 302);
exit;
