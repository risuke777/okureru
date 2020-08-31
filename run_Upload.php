<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="css/styles.css">
</head>

<body>
    <?php
  //DBから取得して表示する．
  $dsn = '*******';
  $user =  '*******';
  $password = '*******';
  $pdo = new PDO($dsn, $user, $password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));

  //画像・動画の場合
  $sql = "SELECT * FROM media where id=:id and u_name=:name;";
  $media_import = $pdo->prepare($sql);
  $media_import->bindValue(":id", $id, PDO::PARAM_STR);
  $media_import->bindValue(":name", $name, PDO::PARAM_STR);
  $media_import->execute();
  //echo var_dump($row = $stmt->fetch(PDO::FETCH_ASSOC));
  while ($row = $media_import->fetch(PDO::FETCH_ASSOC)) {
    //echo ($row["fname"] . "<br/>");
    //動画と画像で場合分け
    $target = $row["fname"];
    if ($row["extension"] == "mp4") {
      echo ("<video src=\"import_media.php?target=$target\" width=\"426\" height=\"240\" controls></video>");
    } elseif ($row["extension"] == "jpeg" || $row["extension"] == "png" || $row["extension"] == "gif") {
      echo ("<img src='import_media.php?target=$target'>");
    } else {
      //ファイル
      //echo var_dump($row);
      if ($row['fplace']) {
        //echo $result[0]['fplace'] . "<br><br><br>";
        header("content-type: application/octet-stream");
        header("content-disposition: attachment; filename={$row['fplace_name']}");
        header("content-length: " . filesize($result['fplace']));
        header("connection: close");
        readfile($row['fplace']);
      }
    }
    echo ("<br/><br/>");
  }


  ?>
</body>

</html>
