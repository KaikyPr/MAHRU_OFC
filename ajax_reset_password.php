<?php
session_start();
require_once("db/conexao.php");

header("Content-Type: application/json");

$response = ["success" => false, "message" => "Erro desconhecido ao redefinir senha."];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["email"]) && isset($_POST["code"]) && isset($_POST["new_password"])) {
    
    $email = filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL);
    $code = trim($_POST["code"]);
    $new_password = $_POST["new_password"]; // Não sanitizar aqui, será hasheada

    // Validações básicas
    if (!$email) {
        $response["message"] = "Formato de email inválido.";
        echo json_encode($response);
        exit;
    }
    if (!ctype_digit($code) || strlen($code) !== 6) { // Assumindo código de 6 dígitos
        $response["message"] = "Formato de código inválido.";
        echo json_encode($response);
        exit;
    }
    if (empty($new_password) || strlen($new_password) < 6) { // Exemplo: exigir senha mínima de 6 caracteres
        $response["message"] = "A nova senha deve ter pelo menos 6 caracteres.";
        echo json_encode($response);
        exit;
    }

    try {
        // 1. Buscar usuário pelo email
        $sql_find = "SELECT id, reset_code, reset_expires FROM usuarios WHERE email = :email LIMIT 1";
        $stmt_find = $pdo->prepare($sql_find);
        $stmt_find->bindParam(":email", $email);
        $stmt_find->execute();

        if ($stmt_find->rowCount() > 0) {
            $usuario = $stmt_find->fetch(PDO::FETCH_ASSOC);
            $user_id = $usuario["id"];
            $db_code = $usuario["reset_code"];
            $db_expires = $usuario["reset_expires"];

            // 2. Verificar código e expiração
            if ($db_code === null || $db_expires === null) {
                 $response["message"] = "Nenhuma solicitação de reset ativa encontrada para este email.";
            } elseif ($db_code !== $code) {
                $response["message"] = "Código de recuperação inválido.";
            } elseif (strtotime($db_expires) < time()) {
                $response["message"] = "Código de recuperação expirado. Solicite um novo.";
                // Opcional: Limpar código expirado do banco aqui
                $sql_clear_expired = "UPDATE usuarios SET reset_code = NULL, reset_expires = NULL WHERE id = :id";
                $stmt_clear_expired = $pdo->prepare($sql_clear_expired);
                $stmt_clear_expired->bindParam(":id", $user_id);
                $stmt_clear_expired->execute();
            } else {
                // 3. Código válido! Hashear nova senha
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // 4. Atualizar senha e limpar código de reset no banco
                $sql_update = "UPDATE usuarios SET senha = :new_password, reset_code = NULL, reset_expires = NULL WHERE id = :id";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(":new_password", $hashed_password);
                $stmt_update->bindParam(":id", $user_id);

                if ($stmt_update->execute()) {
                    $response["success"] = true;
                    $response["message"] = "Senha redefinida com sucesso!";
                } else {
                    $response["message"] = "Erro ao atualizar a senha no banco de dados.";
                }
            }
        } else {
            // Email não encontrado (embora o primeiro script já devesse ter verificado)
            $response["message"] = "Email não encontrado.";
        }

    } catch (PDOException $e) {
        error_log("Erro PDO em ajax_reset_password: " . $e->getMessage());
        $response["message"] = "Erro no banco de dados ao redefinir a senha.";
    } catch (Exception $e) {
        error_log("Erro geral em ajax_reset_password: " . $e->getMessage());
        $response["message"] = "Ocorreu um erro inesperado ao redefinir a senha.";
    }

} else {
    $response["message"] = "Requisição inválida para redefinir senha.";
}

echo json_encode($response);
?>
