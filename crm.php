<?php
ini_set('max_execution_time', 300);
set_time_limit(300);

class Migration {
    private $conn;

    public function __construct()
    {
        $this->getDatabase();
        $this->search();
    }

    private function shutdownPc()
    {
        // Comando de desligamento específico para Windows
        $command = 'shutdown.exe /s /t 0';

        // Executa o comando
        exec($command, $output, $returnCode);

        // Verifica se o comando foi executado com sucesso
        if ($returnCode === 0) {
            echo "O computador está sendo desligado...";
        } else {
            echo "Ocorreu um erro ao tentar desligar o computador.";
        }        
    }

    private function save($obj, $arg, $currentDate)
    {
        $idDeal = $obj->id;
        $idUser = $obj->user->id;

        $getNotes = $this->getNotes($idDeal);
        $notes = $this->formatNotes($getNotes);

        $createdAtPeriod = $currentDate;
        $json = json_encode($obj, JSON_UNESCAPED_UNICODE);
        $status = "Preparado";

        $stmt = $this->conn->prepare("INSERT INTO crm_list (created_at_period, id_user, id_deal, json, status, notes) VALUES (:created_at_period, :id_user, :id_deal, :json, :status, :notes) ");
        
        $stmt->bindValue(":created_at_period", $createdAtPeriod, PDO::PARAM_STR);
        $stmt->bindValue(":id_user", $idUser, PDO::PARAM_STR);
        $stmt->bindValue(":id_deal", $idDeal, PDO::PARAM_STR);
        $stmt->bindValue(":json", $json, PDO::PARAM_STR);
        $stmt->bindValue(":status", $status, PDO::PARAM_STR);
        $stmt->bindValue(":notes", $notes, PDO::PARAM_STR);
    
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                return $this->conn->lastInsertId();
            }
        }
        
        return false;
    }

    private function search()
    {
        $userUnity = $this->getUserUnity();

        if (empty($userUnity)) {
            echo "FIM";
            $this->shutdownPc();
            die();
        }
        
        $url = 'https://crm.rdstation.com/api/v1/deals?token=TOKEN-AQUI&user_id={{ID_USER_UNITY}}&limit=100&created_at_period=1&start_date={{DATE}}T00:00:00&end_date={{DATE}}T23:56:04&page={{PAGE}}';

        $url = str_replace("{{ID_USER_UNITY}}", $userUnity->script_id_user_unity, $url);
        $url = str_replace("{{DATE}}", $userUnity->script_current_date, $url);
        $url = str_replace("{{PAGE}}", $userUnity->script_current_page, $url);

        $curl = curl_init();
    
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));
    
        $response = curl_exec($curl);
        $error = curl_error($curl);

        curl_close($curl);

        if ($error) {
            echo "ERRO";
            die();
        }   

        $response = json_decode($response);

        $deals = $response->deals;

        array_walk($deals, [$this, 'save'], $userUnity->script_current_date);

        if ($response->has_more == true) {
            $newCurrentDate = $userUnity->script_current_date;
            $newCurrenPage = $userUnity->script_current_page + 1;
        } else {
            $newCurrentDate = date('Y-m-d', strtotime($userUnity->script_current_date . '+ 1 days'));
            $newCurrenPage = 1;
        }

        $this->alterCurrentDate($userUnity->id, $newCurrentDate);
        $this->alterCurrentPage($userUnity->id, $newCurrenPage);

        if (date('Y-m-d', strtotime($userUnity->script_current_date)) == $userUnity->script_final_date) {
            echo "PROXIMOUSUARIO";
            $this->alterStatus($userUnity->id);
        } else {
            echo "CONTINUA";
        }
    }

    private function getNotes($idDeal)
    {
        if(empty($idDeal)) {
            return '';
        }

        $url = 'https://crm.rdstation.com/api/v1/activities?token=TOKEN-AQUI&deal_id={{ID_DEAL}}';

        $url = str_replace("{{ID_DEAL}}", $idDeal, $url);

        $curl = curl_init();
    
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));
    
        $response = curl_exec($curl);
        $error = curl_error($curl);

        curl_close($curl);

        if ($error) {
            return false;
        }   

        $response = json_decode($response);

        return $response;

    }

    private function formatNotes($notes) {
        $textNotes = '';

        if(is_object($notes)) {
            $notesInfo = $notes->activities;

            foreach($notesInfo as $info) {
                $date = substr($info->date, 0, 10);
                $date = date('d/m/Y', strtotime($date));

                $textNotes .= $date;
                $textNotes .= ' - ';
                $textNotes .= $info->text;
                $textNotes .= "\n";
            }
        }

        return $textNotes;
    }

    private function alterStatus($id)
    {
        $stmt = $this->conn->prepare("UPDATE crm_config SET script_status = :script_status WHERE id = :id");
        $stmt->bindValue(":script_status", "OK", PDO::PARAM_STR);
        $stmt->bindValue(":id", $id, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return true;
        }
    
        return false;
    }    

    private function alterCurrentPage($id, $value)
    {
        $stmt = $this->conn->prepare("UPDATE crm_config SET script_current_page = :script_current_page WHERE id = :id");
        $stmt->bindValue(":script_current_page", $value, PDO::PARAM_STR);
        $stmt->bindValue(":id", $id, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return true;
        }
    
        return false;
    }

    private function alterCurrentDate($id, $value)
    {
        $stmt = $this->conn->prepare("UPDATE crm_config SET script_current_date = :script_current_date WHERE id = :id");
        $stmt->bindValue(":script_current_date", $value, PDO::PARAM_STR);
        $stmt->bindValue(":id", $id, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return true;
        }
    
        return false;
    }

    private function getUserUnity()
    {
        $stmt = $this->conn->prepare("SELECT * FROM crm_config WHERE script_status = 'Preparado' LIMIT 1");

        if ($stmt->execute()) {
            return $stmt->fetch(PDO::FETCH_OBJ);
        }
    
        return false;
    }

    private function getDatabase()
    {
        $dbServer = "";
        $dbName = "";
        $dbUser = "";
        $dbPassword = "";
        $dbDriver = 'mysql:dbname='. $dbName .';host=' . $dbServer;
    
        $conn = new PDO($dbDriver,$dbUser,$dbPassword);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->exec("set names utf8");
    
        $this->conn = $conn;
    }
}

if (isset($_GET["init"])) {
    if ($_GET["init"] == "S") {
        new Migration();
        die();
    }
}
?> 

<script>
function send() {
    fetch("http:///localhost/crm.php?init=S")
        .then(() => {
            // A solicitação foi bem-sucedida, agora verifique a resposta
            const data = "CONTINUA"; // Substitua esta variável pelo dado real da resposta do servidor
            if (data === "CONTINUA" || data === "PROXIMOUSUARIO") {
                send();
            } else {
                // Alguma ação específica caso não seja CONTINUA ou PROXIMOUSUARIO
                // Por exemplo, chamar send() novamente ou mostrar uma mensagem de erro
                //send(); // Aqui estou chamando send() novamente em caso de outra resposta
            }
        })
        .catch(error => {
            console.error("Ocorreu um erro na solicitação:", error);
            // Chamar send() novamente em caso de falha
            send();
        });
}

send();
</script>