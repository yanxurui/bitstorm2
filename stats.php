<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Stats of the tracker</title>
	<link rel="stylesheet" type="text/css" href="//cdn.datatables.net/1.10.10/css/jquery.dataTables.min.css">
	<script type="text/javascript" src="http://code.jquery.com/jquery.min.js"></script>
	<script type="text/javascript" src="//cdn.datatables.net/1.10.10/js/jquery.dataTables.min.js"></script>
	<script type="text/javascript">
	$(document).ready(function(){
    $('#myTable').DataTable();
    });
	</script>
</head>
<body>
<div>
<table id="myTable" class="display">
    <thead>
        <tr>
            <th>info_hash</th>
            <th>peer_id</th>
            <th>ip4</th>
            <th>ip6</th>
            <th>port</th>
            <th>seed</th>
        </tr>
    </thead>
    <tbody>
        <?php
        require 'vendor/autoload.php';
        $r = new Predis\Client();

        $torrents=$r->smembers('torrents');
        foreach($torrents as $info_hash)
        {
          $peers=$r->smembers($info_hash);
          foreach($peers as $peer_id)
          {
            $temp=$r->hmget($info_hash.':'.$peer_id,'ip4','ip6','port','seed');
            if(!$temp[3])
              $temp[3]='0';
            echo '<tr>';
            echo '<td>'.bin2hex($info_hash).'</td>', 
                 '<td>'.bin2hex($peer_id).'</td>', 
                 '<td>'.$temp[0].'</td>', 
                 '<td>'.$temp[1].'</td>', 
                 '<td>'.$temp[2].'</td>', 
                 '<td>'.$temp[3].'</td>';
            echo '</tr>';
          }
        }
        ?>
    </tbody>
</table>
</div>
</body>
</html>