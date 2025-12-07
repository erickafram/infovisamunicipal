<?php
require_once 'api.php';
?>

<!doctype html>
<html lang="pt-Br">
  <head>
    <title>Consulta CNAE</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
  </head>
  <body>
    <div class="container">
    <h1>Consulta CNAE</h1>
        <form action="" method="get">
            <div class="form-group">
                <input type="text" name="cnae" id="cnae" maxlength="5" class="form-control" placeholder="CNAE" required>
                <?php if (isset($erro)): ?>
                    <div class="alert alert-danger" role="alert"><?= $erro ?></div>
                <?php endif ?>
                <button class="btn btn-primary">Consultar</button>
            </div>
        </form>
    </div>
    
    <div class="container">
        <table class="table">
          <tr>
            <th>CNAE</th>
            <td><?= $result['id'] ?></td>
          </tr>
          <tr>
            <th>Descrição</th>
            <td><?= $result['descricao'] ?></td>
          </tr>
          <tr>
            <th>Grupo</th>
            <td><?= $result['grupo']['id'] . " - " . $result['grupo']['descricao'] ?></td>
          </tr>
          <tr>
            <th>Observações</th>
            <td>
              <?= $observacao ?>
            </td>
          </tr>
        </table>
    </div>
  </body>
</html>