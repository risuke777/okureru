<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>QRコードでシェア-オクレル</title>
    <link rel="shortcut icon" href="favicon.png">
    <meta name="description" content="QRコードシェアサイト。オクレルです。">
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <header>
        <div class="container">
            <p>QRコードでシェア</p>
            <h1>オクレル</h1>
        </div>
    </header>

    <?php //データベース作成
  $dsn = '*******';
  $user =  '*******';
  $password = '*******';
  $pdo = new PDO($dsn, $user, $password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));

  //$sql = "drop table tb_list";$stmt = $pdo->query($sql);

  $sql = "CREATE TABLE IF NOT EXISTS tb_list"
    . " ("
    . "id INT AUTO_INCREMENT PRIMARY KEY,"
    . "name char(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL UNIQUE,"
    . "pw TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL "
    . ");";
  $stmt = $pdo->query($sql);

  //$sql = "drop table media";$stmt = $pdo->query($sql);
  $sql = "CREATE TABLE IF NOT EXISTS media"
    . " ("
    . "id INT AUTO_INCREMENT PRIMARY KEY,"
    . "u_name TEXT CHARACTER SET utf8 COLLATE utf8_general_ci,"
    //. "page_url TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,"
    . "url TEXT CHARACTER SET utf8 COLLATE utf8_general_ci,"
    . "text TEXT CHARACTER SET utf8 COLLATE utf8_general_ci,"
    . "fname TEXT CHARACTER SET utf8 COLLATE utf8_general_ci,"
    . 'extension TEXT CHARACTER SET utf8 COLLATE utf8_general_ci,'
    . 'raw_data LONGBLOB,'
    . "fplace TEXT CHARACTER SET utf8 COLLATE utf8_general_ci,"
    . "fplace_name TEXT CHARACTER SET utf8 COLLATE utf8_general_ci,"
    . 'qr_url TEXT CHARACTER SET utf8 COLLATE utf8_general_ci'
    . ");";
  $stmt = $pdo->query($sql);

  function url_check($url)
  {
    //////// URLの有無確認に curl関数を使う解法 /////////
    // セキュリティ上 サーバ環境設定で allow_url_fopen=0 としている為

    $curl = curl_init(); // はじめ

    //オプション
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET'); //'HEAD' でもできるが時間かかりタイムアウトになる事も
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, true);
    $html =  curl_exec($curl);
    //var_dump($html);
    curl_close($curl); //終了

    if (preg_match('/[2][0-9][0-9]|[3][0-9][0-9]/', $html)) {
      $url_check = "URLは存在します";
    } else {
      $url_check = "";
    }
    return $url_check;
  }
  ?>

    <?php
  $sign_input_alert = "";
  $sign_in_error_ms = "";
  $sign_up_error_ms = "";
  $sign_name = "";
  $sign_hidden = "";
  $maker_hidden = "hidden";
  $show_hidden = "hidden";
  $transition = "check";
  $url_check = "";
  $url = "";
  $text = "";
  $send_qr = "";
  $flag = "";
  $file_up_hidden = "";

  /*$sql = 'SHOW TABLES';
  $result = $pdo->query($sql);
  foreach ($result as $row) {
    echo $row[0];
    echo '<br>';
  }
  echo "<hr>";*/

  /*$sql = 'SELECT * FROM tb_list';
  $stmt = $pdo->query($sql); //prepareを使わずにSQL文を実行
  $results = $stmt->fetchAll();
  foreach ($results as $row) {
    //$rowの中にはテーブルのカラム名が入る
    echo $row['id'] . ',';
    echo $row['name'] . ',';
    echo $row['pw'] . '<br>';
    echo "<hr>";
    echo "<hr>";
  }
  $sql = 'SELECT * FROM media';
  $stmt = $pdo->query($sql); //prepareを使わずにSQL文を実行
  $results = $stmt->fetchAll();
  foreach ($results as $row) {
    //$rowの中にはテーブルのカラム名が入る
    echo $row['id'] . ',';
    echo $row['u_name'] . ',';
    echo $row['text'] . ',';
    //echo $row['fname'] . ',';
    //echo $row['extension'] . ',';
    //echo $row['raw_data'] . ',';
    echo $row['fplace_name'] . ',';
    echo $row['qr_url'] . '<br>';
    echo "<hr>";
  }*/
  //セッション開始

  session_start();

  if ($_SERVER['REQUEST_METHOD'] == "POST") {

    //ページの引継ぎ
    if (!empty($_POST['transition'])) {
      $transition = $_POST['transition'];
    }

    //sign_out はcheckへ
    if (!empty($_POST['sign_out'])) {
      $transition = "check";
      $_SESSION["sign_name"] = '';
      $_SESSION['id'] = "";
      $_SESSION['write'] = '';
      $_SESSION['fplace'] = '';
      $_SESSION['fplace_name'] = '';
      $_SESSION['url'] = '';
      $_SESSION['text'] = '';
      $_SESSION['filename'] = "";
    }

    //URL確認
    if (!empty($_POST['url_check'])) {
      $url = $_POST['url']; //前回入力の内容を繰り返し表示
      $text = $_POST['text']; //前回入力の内容を繰り返し表示
      $transition = "maker";
      if (url_check($url)) { //URL確認
        $url_check = url_check($url);
      } else {
        $url_check = "URLは存在しません！";
      }
    }

    //upfileがあった場合
    try {
      if (isset($_FILES['upfile']['error']) && is_int($_FILES['upfile']['error']) && $_FILES["upfile"]["name"] !== "") {
        //エラーチェック
        switch ($_FILES['upfile']['error']) {
          case UPLOAD_ERR_OK: // OK
            break;
          case UPLOAD_ERR_NO_FILE:   // 未選択
            throw new RuntimeException('ファイルが選択されていません', 400);
          case UPLOAD_ERR_INI_SIZE:  // php.ini定義の最大サイズ超過
            throw new RuntimeException('ファイルサイズが大きすぎます', 400);
          default:
            throw new RuntimeException('その他のエラーが発生しました', 500);
        }

        //画像・動画をバイナリデータにする．
        $raw_data = file_get_contents($_FILES['upfile']['tmp_name']);
        $_SESSION['filename'] = $_FILES['upfile']['name'];
        //拡張子を見る
        $tmp = pathinfo($_FILES["upfile"]["name"]);
        $extension = $tmp["extension"];
        if ($extension === "jpg" || $extension === "jpeg" || $extension === "JPG" || $extension === "JPEG") {
          $extension = "jpeg";
        } elseif ($extension === "png" || $extension === "PNG") {
          $extension = "png";
        } elseif ($extension === "gif" || $extension === "GIF") {
          $extension = "gif";
        } elseif ($extension === "mp4" || $extension === "MP4") {
          $extension = "mp4";
        } else { //ただファイルアップロードする
          $fplace = './upfiles/' . $_FILES['upfile']['name'];
          $fplace_name = $_FILES['upfile']['name'];
          $_SESSION['fplace'] = $fplace;
          move_uploaded_file($_FILES['upfile']['tmp_name'], $fplace);
          $sql = "UPDATE media set fplace=:fplace, fplace_name=:fplace_name WHERE id=:id and u_name=:name;";
          $file_up = $pdo->prepare($sql);
          $file_up->bindValue(":id", $_SESSION['id'], PDO::PARAM_INT);
          $file_up->bindValue(":name", $_SESSION["sign_name"], PDO::PARAM_STR);
          $file_up->bindValue(":fplace", $fplace, PDO::PARAM_STR);
          $file_up->bindValue(":fplace_name", $fplace_name, PDO::PARAM_STR);
          $file_up->execute();
          $flag = "1";
          $_SESSION['write'] = '1';
          $_SESSION['filename'] = $_FILES['upfile']['name'];
          //echo $filename . "をアップロードしました。";
          //echo "ファイルをアップロードできません。";

          //echo "非対応ファイルです．<br/>";
          //echo ("<a href=\"main.php\">戻る</a><br/>");
          //exit(1);
        }

        if ($flag != "1") {
          //DBに格納するファイルネーム設定
          //サーバー側の一時的なファイルネームと取得時刻を結合した文字列にsha256をかける．
          $date = getdate();
          $fname = $_FILES["upfile"]["tmp_name"] . $date["year"] . $date["mon"] . $date["mday"] . $date["hours"] . $date["minutes"] . $date["seconds"];
          $fname = hash("sha256", $fname);

          //画像・動画をDBに格納．
          $sql = "UPDATE media set fname=:fname, extension=:extension, raw_data=:raw_data WHERE id=:id and u_name=:name;";
          $file_up = $pdo->prepare($sql);
          $file_up->bindValue(":id", $_SESSION['id'], PDO::PARAM_INT);
          $file_up->bindValue(":name", $_SESSION["sign_name"], PDO::PARAM_STR);
          $file_up->bindValue(":fname", $fname, PDO::PARAM_STR);
          $file_up->bindValue(":extension", $extension, PDO::PARAM_STR);
          $file_up->bindValue(":raw_data", $raw_data, PDO::PARAM_STR);
          $file_up->execute();
          $_SESSION['write'] = '1';
        }
      } //ファイルアップロード作業終了
    } catch (PDOException $e) {
      echo ("<p>500 Inertnal Server Error</p>");
      //exit($e->getMessage());
      echo $e->getMessage();
    }

    //QR再度作成
    if (!empty($_POST['make_again'])) {
      $transition = "maker";
      //session IDリセット
      $_SESSION['id'] = "";
      $_SESSION['write'] = '';
      $_SESSION['fplace'] = '';
      $_SESSION['fplace_name'] = '';
      $_SESSION['url'] = '';
      $_SESSION['text'] = '';
      $_SESSION['filename'] = "";
      //管理id発行のため一時的に書き込み
      $sql1 = "INSERT INTO media(u_name) VALUES (:name);";
      $stmt = $pdo->prepare($sql1);
      $stmt->bindparam(":name", $_SESSION["sign_name"], PDO::PARAM_STR);
      $stmt->execute();

      //書き込んでいくデータのidを保存
      $sql2 = "SELECT MAX(id) FROM media where u_name=:name;";
      $stmt = $pdo->prepare($sql2);
      $stmt->bindValue(":name", $_SESSION["sign_name"], PDO::PARAM_STR);

      $stmt->execute();
      $result = $stmt->fetchall(PDO::FETCH_ASSOC);
      //echo var_dump($result);
      $_SESSION['id'] = $result[0]["MAX(id)"];
    }

    //echo $transition . "<br>";
    if ($transition == "check") {
      //session_clear
      /*$_SESSION['url'] = "";
      $_SESSION['id'] = "";
      $_SESSION['text'] = "";
      $_SESSION['write'] = '';
      $_SESSION['fplace'] = '';
      $_SESSION["sign_name"] = "";
      $_SESSION['filename'] = "";*/

      if (!empty($_POST['sign_name'])) {
        if (!empty($_POST['sign_pw'])) {
          $sign_name = $_POST['sign_name'];
          $sign_pw = $_POST['sign_pw'];
          if (!empty($_POST['sign_type'])) {

            if ($_POST['sign_type'] == 1) {
              //データベース名前存在チェック
              $sql = 'SELECT * FROM tb_list where name = :name';
              $stmt = $pdo->prepare($sql);
              $stmt->bindParam(':name', $sign_name, PDO::PARAM_STR);
              $stmt->execute();
              $result = $stmt->fetchall(PDO::FETCH_ASSOC);

              if ($result) {

                if ($result[0]['pw'] == $sign_pw) {
                  //QR作成ページ遷移
                  $_SESSION['id'] = "";
                  $_SESSION['write'] = '';
                  $_SESSION['fplace'] = '';
                  $_SESSION['fplace_name'] = '';
                  $_SESSION['url'] = '';
                  $_SESSION['text'] = '';
                  $_SESSION['filename'] = "";
                  $_SESSION["sign_name"] = $sign_name;
                  $transition = "maker";
                  $sign_hidden = "hidden";
                  $maker_hidden = "";
                  $show_hidden = "hidden";
                } else {
                  $sign_in_error_ms = "パスワードが違います";
                }
              } else {
                $sign_in_error_ms = "登録されていない名前です";
              }
            } else {
              //echo $_POST['sign_type'];
              //データベース名前存在チェック
              //$sql = 'SELECT *FROM tb_list where name = :name';
              //$stmt = $pdo->prepare('INSERT INTO tb_list (name, pw) VALUES (:name, :password)');

              $sql = 'SELECT *FROM tb_list WHERE name=:name';
              $stmt = $pdo->prepare($sql);
              $stmt->bindParam(':name', $sign_name, PDO::PARAM_STR);
              $stmt->execute();
              $name_result = $stmt->fetchall(PDO::FETCH_ASSOC);
              //echo var_dump($name_result);

              if ($name_result) {
                $sign_up_error_ms = "既に登録されている名前です";
              } else {
                //データベースに情報を登録し、QR作成ページ遷移
                $_SESSION['id'] = "";
                $_SESSION['write'] = '';
                $_SESSION['fplace'] = '';
                $_SESSION['fplace_name'] = '';
                $_SESSION['url'] = '';
                $_SESSION['text'] = '';
                $_SESSION['filename'] = "";
                $_SESSION["sign_name"] = $sign_name;
                $transition = "maker";
                $sign_hidden = "hidden";
                $maker_hidden = "";
                $show_hidden = "hidden";
                /*$user_add = $pdo->prepare('INSERT INTO tb_list(name, pw) VALUES (:name, :pw) ON DUPLICATE KEY UPDATE name = VALUES (name), pw = VALUES (pw)');
                $user_add->bindParam(':name', $sign_name, PDO::PARAM_STR);
                $user_add->bindParam(':pw', $sign_pw, PDO::PARAM_STR);
                $user_add->execute();*/

                $user_add = $pdo->prepare('INSERT INTO tb_list (name, pw) VALUES (:name, :password)');
                $user_add->bindParam(':name', $sign_name, PDO::PARAM_STR);
                $user_add->bindParam(':password', $sign_pw, PDO::PARAM_STR);
                $user_add->execute();
              }
            }
          }
        } else {
          $sign_input_alert = "パスワードが入力されていません";
        }
      } else {
        $sign_input_alert = "名前が入力されていません";
      }


      //QRコード作成ページ  
    } else if ($transition == 'maker') {
      if (empty($_SESSION['id'])) {
        //管理id発行のため一時的に書き込み
        $sql1 = "INSERT INTO media(u_name) VALUES (:name);";
        $make_id = $pdo->prepare($sql1);
        $make_id->bindparam(":name", $_SESSION["sign_name"], PDO::PARAM_STR);
        $make_id->execute();

        //書き込んでいくデータのidを保存
        $sql2 = "SELECT MAX(id) FROM media where u_name=:name;";
        $stmt = $pdo->prepare($sql2);
        $stmt->bindValue(":name", $_SESSION["sign_name"], PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchall(PDO::FETCH_ASSOC);
        //echo var_dump($result);
        $_SESSION['id'] = $result[0]["MAX(id)"];
      }

      if ($_SESSION['filename']) {
        $file_up_hidden = "hidden";
      }
      /*echo "id:" . $_SESSION['id'] . "<br>";
      echo "write:" . $_SESSION['write'] . "<br>";
      echo "fplace:" . $_SESSION['fplace'] . "<br>";
      echo "url:" . $_SESSION['url'] . "<br>";
      echo "filename:" . $_SESSION['filename'] . "<br>";
      echo "text:" . $_SESSION['text'] . "<br>";*/

      if (!empty($_POST['url'])) { //URLありの場合
        if (url_check($_POST['url'])) {
          $_SESSION['url'] = $_POST['url'];
        } else {
          $url_check = "URLが存在しません！";
          $_SESSION['write'] = "";
        }
      }

      if (!empty($_POST['text'])) { //TEXTありの場合
        $_SESSION['text'] = $_POST['text'];
        $_SESSION['write'] = '1';
      }

      if (!empty($_POST['make'])) { //書き込み作業開始

        if ($_SESSION['write'] == "1") {
          $filename = $_SESSION['sign_name'] . $_SESSION['id'] . '.php';
          $fp = fopen($filename, "a");
          //ページに書き込む中身
          $contents = "
          <!DOCTYPE html><html><head>
          <meta charset=\"utf-8\">
          <style>
         .container {
            width: 90%;
            text-align: center;
          }

          .first-sentence {
            font-weight: bold;
         }
         </style>
          </head>
          <body>
          <a href=" . $_SESSION['url'] . " target=\"_blank\">" . $_SESSION['url'] . "</a><br><br><br><p>"
            . $_SESSION['text'] . "</p><br><br><br>

          <?php
          \$name = \"" . $_SESSION['sign_name'] . "\";
          \$id = \"" . $_SESSION['id'] . "\";
          include_once(\"run.php\");
          ?>

</body>

</html>
";
//上のパラメーター使って表示用run.phpとimport.phpに入れて
//表示用のファイル作成
fwrite($fp, $contents);
fclose($fp);
$send_qr = "<img src=https://chart.apis.google.com/chart?chs=250x250&cht=qr&chl=https://*******/" . $filename . " alt=\" QRコード\">";
$sql = "UPDATE media set qr_url=:qr_url where id=:id and u_name=:name";
$write = $pdo->prepare($sql);
$write->bindParam(':id', $_SESSION['id'], PDO::PARAM_INT);
$write->bindParam(':name', $_SESSION["sign_name"], PDO::PARAM_STR);
$write->bindParam(':qr_url', $send_qr, PDO::PARAM_STR);
$write->execute();
} else {
$send_qr = "<img src=https://chart.apis.google.com/chart?chs=250x250&cht=qr&chl=" . $_SESSION['url'] . " alt=\" QRコード\">";
$sql = "UPDATE media set qr_url=:qr_url where id=:id and u_name=:name";
$write = $pdo->prepare($sql);
$write->bindParam(':id', $_SESSION['id'], PDO::PARAM_INT);
$write->bindParam(':name', $_SESSION["sign_name"], PDO::PARAM_STR);
$write->bindParam(':qr_url', $send_qr, PDO::PARAM_STR);
$write->execute();
}
$sign_hidden = "hidden";
$maker_hidden = "hidden";
$show_hidden = "";
$transition = 'show';
} else {
//そのままmakerへ
$sign_hidden = "hidden";
$maker_hidden = "";
$show_hidden = "hidden";
}
//QRコード表示ページ
} else if ($transition == 'show') {
$sign_hidden = "hidden";
$maker_hidden = "hidden";
$show_hidden = "";
}
} else {
$transition = "check";
}
?>

<div class="sign_box" <?php echo $sign_hidden ?>>
    <!--Sign Inボックス-->
    <form method="POST" action="main.php">
        <input type="radio" name="sign_type" value="1" id="Sign In" checked>Sign In
        <input type="radio" name="sign_type" value="2" id="Sign Up">Sign Up
        <table>
            <tr>
                <td>名前</td>
                <td><input name="sign_name" type="text" placeholder="名前"></td><br>
            </tr>
            <tr>
                <td>パスワード</td>
                <td><input name="sign_pw" type="text" placeholder="パスワード"></td>
            </tr>
        </table>

        <p><?php echo $sign_input_alert ?>
            <?php echo $sign_in_error_ms ?>
            <?php echo $sign_up_error_ms ?></p>

        <input name="transition" type="hidden" value="<?php echo $transition ?>">
        <input type="submit" />
    </form>
</div>

<div class="maker" <?php echo $maker_hidden ?>>
    <div class="sign_info_box">
        <!--Sign Outボックス-->
        <form action="main.php" method="post">
            <td><?php echo $_SESSION["sign_name"] ?></td>
            <td>でサインイン中</td><br>
            <input name="transition" type="hidden" value="check" />
            <input name="sign_out" type="submit" value="sign_out" />
        </form>
    </div>

    <div class="input">
        <h1>QRコードに入力したい内容を入力</h1>
        <div class="input_box">
            <form action="main.php" enctype="multipart/form-data" method="post">
                <table>
                    <tr>
                        <td>URL</td>
                        <td><input name="url" type="text" placeholder="https://" value="<?php echo $_SESSION['url'] ?>"></td>
                        <td><input type="submit" name="url_check" value="URL確認" /></td>
                        <td><?php echo $url_check ?></td>
                        <br>
                    </tr>
                </table>
                <table>
                    <tr>
                        <td>テキスト</td>
                        <td><textarea name="text" type="text" placeholder="テキスト" value="<?php echo $_SESSION['text'] ?>" cols="40"></textarea></td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <td>ファイル・画像</td><?php if ($_SESSION['filename']) {
                                echo "<td><input readonly value=" . $_SESSION['filename'] . "></td>";
                              } ?>
                        <td><input type="file" name="upfile" <?php echo $file_up_hidden ?>></td>
                        <td><input name="transition" type="hidden" value="maker" <?php echo $file_up_hidden ?>></td>
                        <td><input type="submit" value="アップロード" <?php echo $file_up_hidden ?>></td>
                    </tr>
                </table>
                <input name="session_id" type="hidden" value="<?php ?>">
                <input name="transition" type="hidden" value="maker">
                <td><input style="position:relative; top:100px;" type="submit" name="make" value="QRコード作成" /></td>
            </form>


        </div>
    </div>

</div>


<div class="show" <?php echo $show_hidden ?>>
    <div class="sign_info_box">
        <!--Sign Outボックス-->
        <form action="main.php" method="post">
            <td><?php echo $_SESSION["sign_name"] ?></td>
            <td>でサインイン中</td><br>
            <input name="transition" type="hidden" value="check" />
            <input name="sign_out" type="submit" value="sign_out" />
        </form>
    </div>

    <?php echo $send_qr ?>
    <form action="main.php" method="post">
        <input name="make_again" type="submit" value="他のQRコードを作成する" />
    </form>

</div>

</body>

</html>
