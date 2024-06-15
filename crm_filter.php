<?php

class Filter
{
    private $conn;

    public function __construct()
    {
        $this->getDatabase();
        $this->search();
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

    private function search()
    {

        // acessar a tabela crm list pelo id
        // pegar o campo json
        // acessar a parte do email e telefone do cliente
        // salvar eles na mesma linha
        

        $lead = $this->getFirstLead();

        if (empty($lead)) {
            echo "FIM";
            //$this->shutdownPc();
            die();
        }

        $this->updateLeads($lead);

        echo "CONTINUA";
        die();
    }

    private function getFirstLead()
    {
        $stmt = $this->conn->prepare("SELECT * FROM crm_list WHERE status = 'Preparado' LIMIT 1");

        if ($stmt->execute()) {
            return $stmt->fetch(PDO::FETCH_OBJ);
        }
    
        return [];
    }

    private function updateLeads($lead)
    {
        $leadExtracted = $this->extractInformation($lead); 

        $stmt = $this->conn->prepare("UPDATE crm_list SET status = :status, email_aluno = :email_aluno, telefone_aluno = :telefone_aluno WHERE id = :id");
        $stmt->bindValue(":id", $leadExtracted->id, PDO::PARAM_INT);
        $stmt->bindValue(":status", $leadExtracted->status, PDO::PARAM_STR);
        $stmt->bindValue(":email_aluno", $leadExtracted->email_aluno, PDO::PARAM_STR);
        $stmt->bindValue(":telefone_aluno", $leadExtracted->telefone_aluno, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return true;
        }
    
        return false;

    }

    private function extractInformation($lead)
    {

        $json = json_decode($lead->json);

        if (isset($json->contacts[0]->emails[0]->email)) {
            $lead->email_aluno = $json->contacts[0]->emails[0]->email;
        }

        /*-- TELEFONE DO CLIENTE --*/

        if (isset($json->contacts[0]->phones[0]->phone)) {
            $lead->telefone_aluno = $json->contacts[0]->phones[0]->phone;
        }

        $lead->status = "OK";

        return $lead;
    }

}

if (isset($_GET["init"])) {
    if ($_GET["init"] == "S") {
        new Filter();
        die();
    }
}
?> 

<script>
function send() {
    fetch("http:///localhost/crm_filter.php?init=S")
    .then(response => response.text())
    .then(data => {
        if (data != "FIM") {
            send();
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