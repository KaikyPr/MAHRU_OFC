<?php
session_start();
require_once("db/conexao.php");

// Verificar se já está logado
if (isset($_SESSION["usuario_id"])) {
    header("Location: painel.php");
    exit;
}

// Verificar e criar colunas necessárias no banco de dados
try {
    // Verificar se as colunas existem
    $sql_check_columns = "SHOW COLUMNS FROM usuarios LIKE 'reset_password_code'";
    $result = $pdo->query($sql_check_columns);
    
    if ($result->rowCount() == 0) {
        // Adicionar coluna reset_password_code
        $sql_add_column = "ALTER TABLE usuarios ADD COLUMN reset_password_code VARCHAR(10)";
        $pdo->exec($sql_add_column);
    }
    
    $sql_check_columns = "SHOW COLUMNS FROM usuarios LIKE 'reset_password_expires'";
    $result = $pdo->query($sql_check_columns);
    
    if ($result->rowCount() == 0) {
        // Adicionar coluna reset_password_expires
        $sql_add_column = "ALTER TABLE usuarios ADD COLUMN reset_password_expires DATETIME";
        $pdo->exec($sql_add_column);
    }
} catch (PDOException $e) {
    // Registrar erro, mas continuar a execução
    error_log("Erro ao verificar/criar colunas: " . $e->getMessage());
}

// Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require "vendor/phpmailer/phpmailer/src/Exception.php";
require "vendor/phpmailer/phpmailer/src/PHPMailer.php";
require "vendor/phpmailer/phpmailer/src/SMTP.php";

// Mensagens de erro/sucesso
$mensagem = "";
$tipo_mensagem = "";

// Controle de etapa do processo
$etapa = isset($_GET['etapa']) ? intval($_GET['etapa']) : 1;
if ($etapa < 1 || $etapa > 3) {
    $etapa = 1; // 1 = Solicitar email, 2 = Verificar código, 3 = Definir nova senha
}

// Processar solicitação de recuperação
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Etapa 1: Solicitar código de recuperação
    if (isset($_POST["solicitar_codigo"])) {
        $email = filter_input(INPUT_POST, "email", FILTER_VALIDATE_EMAIL);
        
        if (empty($email)) {
            $mensagem = "Por favor, informe um endereço de e-mail válido.";
            $tipo_mensagem = "error";
        } else {
            try {
                // Verificar se o email existe
                $sql_check = "SELECT id, nome FROM usuarios WHERE email = :email";
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->bindParam(":email", $email);
                $stmt_check->execute();
                
                if ($stmt_check->rowCount() > 0) {
                    $usuario = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    
                    // Gerar código e data de expiração
                    $codigo = substr(str_shuffle("0123456789"), 0, 6);
                    $expira = new DateTime("+15 minutes");
                    $expira_formatado = $expira->format("Y-m-d H:i:s");
                    
                    // Salvar código no banco
                    $sql_update = "UPDATE usuarios SET reset_password_code = :code, reset_password_expires = :expires WHERE id = :id";
                    $stmt_update = $pdo->prepare($sql_update);
                    $stmt_update->bindParam(":code", $codigo);
                    $stmt_update->bindParam(":expires", $expira_formatado);
                    $stmt_update->bindParam(":id", $usuario["id"]);
                    
                    if ($stmt_update->execute()) {
                        // Enviar e-mail com o código
                        $mail = new PHPMailer(true);
                        try {
                            // Configurações do Servidor
                            $mail->isSMTP();
                            $mail->Host       = "mail.simonatosys.com.br";
                            $mail->SMTPAuth   = true;
                            $mail->Username   = "mahru@simonatosys.com.br";
                            $mail->Password   = "eNbO.Gp)8}lM";
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                            $mail->Port       = 465;
                            
                            // Remetente e Destinatário
                            $mail->setFrom("mahru@simonatosys.com.br", "MAHRU Sistema");
                            $mail->addAddress($email);
                            
                            // Conteúdo
                            $mail->isHTML(true);
                            $mail->Subject = "Redefinir de Senha - MAHRU";
                            $mail->Body    = "Olá " . htmlspecialchars($usuario["nome"]) . ",<br><br>Você solicitou a recuperação de senha para sua conta no MAHRU.<br>Para prosseguir, utilize o código abaixo na página de recuperação de senha:<br><br><b>" . $codigo . "</b><br><br>Este código expira em 15 minutos.<br>Se você não solicitou esta recuperação, ignore este e-mail.<br><br>Atenciosamente,<br>Equipe MAHRU";
                            $mail->AltBody = "Olá " . $usuario["nome"] . ",\n\nVocê solicitou a recuperação de senha para sua conta no MAHRU.\nPara prosseguir, utilize o código abaixo na página de recuperação de senha:\n\n" . $codigo . "\n\nEste código expira em 15 minutos.\nSe você não solicitou esta recuperação, ignore este e-mail.\n\nAtenciosamente,\nEquipe MAHRU";
                            
                            $mail->send();
                            $mensagem = "Código de recuperação enviado para seu e-mail. Verifique sua caixa de entrada.";
                            $tipo_mensagem = "success";
                            
                            // Armazenar email em sessão para próxima etapa
                            $_SESSION["reset_email"] = $email;
                            
                            // Avançar para a próxima etapa
                            $etapa = 2;
                        } catch (Exception $e) {
                            // Falha ao enviar email, mas mostrar código para desenvolvimento
                            $mensagem = "Erro ao enviar e-mail, mas o código foi gerado: " . $codigo;
                            $tipo_mensagem = "warning";
                            
                            // Armazenar email em sessão para próxima etapa
                            $_SESSION["reset_email"] = $email;
                            
                            // Avançar para a próxima etapa
                            $etapa = 2;
                            
                            error_log("Mailer Error: {$e->getMessage()}");
                        }
                    } else {
                        $mensagem = "Erro ao salvar código de recuperação no banco de dados.";
                        $tipo_mensagem = "error";
                    }
                } else {
                    // Por segurança, não informamos que o email não existe
                    $mensagem = "Se o e-mail estiver cadastrado, você receberá um código de recuperação.";
                    $tipo_mensagem = "info";
                }
            } catch (PDOException $e) {
                $mensagem = "Erro no banco de dados ao verificar e-mail: " . $e->getMessage();
                $tipo_mensagem = "error";
                error_log("Exceção PDO ao verificar e-mail: " . $e->getMessage());
            }
        }
    }
    
    // Etapa 2: Verificar código
    elseif (isset($_POST["verificar_codigo"])) {
        $codigo = filter_input(INPUT_POST, "codigo", FILTER_SANITIZE_SPECIAL_CHARS);
        
        if (empty($codigo)) {
            $mensagem = "Por favor, informe o código de recuperação.";
            $tipo_mensagem = "error";
        } elseif (!isset($_SESSION["reset_email"])) {
            $mensagem = "Sessão expirada. Por favor, inicie o processo novamente.";
            $tipo_mensagem = "error";
            $etapa = 1;
        } else {
            try {
                $email = $_SESSION["reset_email"];
                
                // Verificar código
                $sql_check = "SELECT id, reset_password_code, reset_password_expires FROM usuarios WHERE email = :email";
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->bindParam(":email", $email);
                $stmt_check->execute();
                
                if ($stmt_check->rowCount() > 0) {
                    $usuario = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    
                    // Verificar código e expiração
                    $agora = new DateTime();
                    $expira = new DateTime($usuario["reset_password_expires"]);
                    
                    if (empty($usuario["reset_password_code"])) {
                        $mensagem = "Nenhum código de recuperação foi solicitado ou os dados são inválidos.";
                        $tipo_mensagem = "error";
                    } elseif ($codigo !== $usuario["reset_password_code"]) {
                        $mensagem = "Código de recuperação inválido.";
                        $tipo_mensagem = "error";
                    } elseif ($agora > $expira) {
                        $mensagem = "Código de recuperação expirado. Solicite novamente.";
                        $tipo_mensagem = "error";
                        
                        // Limpar dados expirados
                        $sql_clear = "UPDATE usuarios SET reset_password_code = NULL, reset_password_expires = NULL WHERE id = :id";
                        $stmt_clear = $pdo->prepare($sql_clear);
                        $stmt_clear->bindParam(":id", $usuario["id"]);
                        $stmt_clear->execute();
                        
                        $etapa = 1;
                        unset($_SESSION["reset_email"]);
                    } else {
                        // Código válido, avançar para a próxima etapa
                        $mensagem = "Código verificado com sucesso. Defina sua nova senha.";
                        $tipo_mensagem = "success";
                        $etapa = 3;
                        
                        // Armazenar ID do usuário em sessão para próxima etapa
                        $_SESSION["reset_user_id"] = $usuario["id"];
                    }
                } else {
                    $mensagem = "E-mail não encontrado.";
                    $tipo_mensagem = "error";
                    $etapa = 1;
                    unset($_SESSION["reset_email"]);
                }
            } catch (PDOException $e) {
                $mensagem = "Erro no banco de dados ao verificar código: " . $e->getMessage();
                $tipo_mensagem = "error";
                error_log("Exceção PDO ao verificar código: " . $e->getMessage());
            }
        }
    }
    
    // Etapa 3: Definir nova senha
    elseif (isset($_POST["redefinir_senha"])) {
        $nova_senha = $_POST["nova_senha"] ?? "";
        $confirmar_senha = $_POST["confirmar_senha"] ?? "";
        
        if (empty($nova_senha) || empty($confirmar_senha)) {
            $mensagem = "Todos os campos são obrigatórios.";
            $tipo_mensagem = "error";
        } elseif (strlen($nova_senha) < 6) {
            $mensagem = "A nova senha deve ter pelo menos 6 caracteres.";
            $tipo_mensagem = "error";
        } elseif ($nova_senha !== $confirmar_senha) {
            $mensagem = "A nova senha e a confirmação não coincidem.";
            $tipo_mensagem = "error";
        } elseif (!isset($_SESSION["reset_user_id"]) || !isset($_SESSION["reset_email"])) {
            $mensagem = "Sessão expirada. Por favor, inicie o processo novamente.";
            $tipo_mensagem = "error";
            $etapa = 1;
        } else {
            try {
                $usuario_id = $_SESSION["reset_user_id"];
                $email = $_SESSION["reset_email"];
                
                // Atualizar senha
                $hash_nova_senha = password_hash($nova_senha, PASSWORD_DEFAULT);
                
                $sql_update = "UPDATE usuarios SET senha = :senha, reset_password_code = NULL, reset_password_expires = NULL WHERE id = :id";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(":senha", $hash_nova_senha);
                $stmt_update->bindParam(":id", $usuario_id);
                
                if ($stmt_update->execute()) {
                    // Buscar nome do usuário para o e-mail
                    $sql_nome = "SELECT nome FROM usuarios WHERE id = :id";
                    $stmt_nome = $pdo->prepare($sql_nome);
                    $stmt_nome->bindParam(":id", $usuario_id);
                    $stmt_nome->execute();
                    $nome = $stmt_nome->fetchColumn();
                    
                    // Enviar e-mail de confirmação
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = "mail.simonatosys.com.br";
                        $mail->SMTPAuth   = true;
                        $mail->Username   = "mahru@simonatosys.com.br";
                        $mail->Password   = "eNbO.Gp)8}lM";
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        $mail->Port       = 465;
                        
                        $mail->setFrom("mahru@simonatosys.com.br", "MAHRU Sistema");
                        $mail->addAddress($email);
                        
                        $mail->isHTML(true);
                        $mail->Subject = "Senha Redefinida com Sucesso - MAHRU";
                        $mail->Body    = "Olá " . htmlspecialchars($nome) . ",<br><br>Sua senha de acesso ao MAHRU foi redefinida com sucesso.<br><br>Se você não realizou esta alteração, entre em contato com o administrador do sistema imediatamente.<br><br>Atenciosamente,<br>Equipe MAHRU";
                        $mail->AltBody = "Olá " . $nome . ",\n\nSua senha de acesso ao MAHRU foi redefinida com sucesso.\n\nSe você não realizou esta alteração, entre em contato com o administrador do sistema imediatamente.\n\nAtenciosamente,\nEquipe MAHRU";
                        
                        $mail->send();
                    } catch (Exception $e) {
                        error_log("Erro ao enviar e-mail de confirmação de redefinição: " . $mail->ErrorInfo);
                    }
                    
                    $mensagem = "Senha redefinida com sucesso! Você já pode fazer login com sua nova senha.";
                    $tipo_mensagem = "success";
                    
                    // Limpar dados de sessão
                    unset($_SESSION["reset_email"]);
                    unset($_SESSION["reset_user_id"]);
                    
                    // Redirecionar para login após 3 segundos
                    header("Refresh: 3; URL=login.php");
                } else {
                    $mensagem = "Erro ao atualizar senha no banco de dados.";
                    $tipo_mensagem = "error";
                }
            } catch (PDOException $e) {
                $mensagem = "Erro no banco de dados ao redefinir senha: " . $e->getMessage();
                $tipo_mensagem = "error";
                error_log("Exceção PDO ao redefinir senha: " . $e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/Design_sem_nome-removebg-preview.ico" type="image/x-icon">
    <title>Recuperar Senha - MAHRU</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <style>
        /* Estilos gerais */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background-color: #121212; color: white; height: 100vh; display: flex; justify-content: center; align-items: center; background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('img/LOGO.png') center/cover; }
        .container { width: 100%; max-width: 400px; padding: 20px; }
        .wrapper { width: 100%; background-color: #2a2a2a; border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6); overflow: hidden; animation: fadeIn 0.5s ease; }
        .title { background-color: #00aaff; color: white; font-size: 30px; font-weight: 600; text-align: center; line-height: 100px; border-radius: 15px 15px 0 0; user-select: none; }
        .form { padding: 30px 25px 25px 25px; }
        .row { position: relative; margin-bottom: 25px; }
        .row > i:first-child { position: absolute; color: #00aaff; font-size: 20px; top: 50%; transform: translateY(-50%); left: 15px; }
        .toggle-password { position: absolute; cursor: pointer; color: #999; font-size: 18px; top: 50%; transform: translateY(-50%); right: 15px; z-index: 2; }
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%; height: 50px; padding: 0 20px 0 45px; border: 1px solid #444; outline: none; font-size: 16px; border-radius: 8px; background-color: #333; color: white; transition: all 0.3s ease;
        }
        input[type="password"] { padding-right: 45px; } /* Espaço para o ícone de olho */
        input:focus { border-color: #00aaff; box-shadow: 0 0 10px rgba(0, 170, 255, 0.3); }
        input::placeholder { color: #999; }
        .button button {
            width: 100%; height: 50px; background-color: #00aaff; border: none; color: white; font-size: 18px; font-weight: 500; letter-spacing: 1px; cursor: pointer; transition: all 0.3s ease; padding: 0; border-radius: 8px;
        }
        .button button:hover { background-color: #0088cc; transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2); }
        .login-link {
            text-align: center; margin-top: 20px; font-size: 14px;
        }
        .login-link a {
            color: #00aaff; text-decoration: none; transition: all 0.3s ease;
        }
        .login-link a:hover { text-decoration: underline; color: #0088cc; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; text-align: center; font-weight: bold; }
        .alert-success { background-color: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }
        .alert-error { background-color: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid #dc3545; }
        .alert-info { background-color: rgba(23, 162, 184, 0.2); color: #17a2b8; border: 1px solid #17a2b8; }
        .alert-warning { background-color: rgba(255, 193, 7, 0.2); color: #ffc107; border: 1px solid #ffc107; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Estilo para o código de verificação */
        .verification-code {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 25px;
        }
        .verification-code input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            padding: 0;
            border-radius: 8px;
            background-color: #333;
            color: white;
            border: 1px solid #444;
        }
        .verification-code input:focus {
            border-color: #00aaff;
            box-shadow: 0 0 10px rgba(0, 170, 255, 0.3);
        }
        
        /* Indicador de etapas */
        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        .steps::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 10%;
            right: 10%;
            height: 2px;
            background-color: #444;
            z-index: 1;
        }
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #444;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            position: relative;
            z-index: 2;
        }
        .step.active {
            background-color: #00aaff;
        }
        .step.completed {
            background-color: #28a745;
        }
        
        /* Descrição das etapas */
        .step-description {
            text-align: center;
            margin-bottom: 20px;
            color: #ccc;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="wrapper">
            <div class="title">
                <span>Recuperar Senha</span>
            </div>
            
            <div class="form">
                <?php if (!empty($mensagem)): ?>
                    <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                        <?php echo htmlspecialchars($mensagem); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Indicador de etapas -->
                <div class="steps">
                    <div class="step <?php echo $etapa >= 1 ? 'active' : ''; ?> <?php echo $etapa > 1 ? 'completed' : ''; ?>">1</div>
                    <div class="step <?php echo $etapa >= 2 ? 'active' : ''; ?> <?php echo $etapa > 2 ? 'completed' : ''; ?>">2</div>
                    <div class="step <?php echo $etapa >= 3 ? 'active' : ''; ?>">3</div>
                </div>
                
                <?php if ($etapa === 1): ?>
                    <!-- Etapa 1: Solicitar código de recuperação -->
                    <div class="step-description">
                        <p>Digite seu e-mail cadastrado para receber um código de recuperação.</p>
                    </div>
                    
                    <form method="post" action="recuperar_senha.php">
                        <div class="row">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" name="email" placeholder="Seu E-mail cadastrado" required>
                        </div>
                        <div class="row button">
                            <button type="submit" name="solicitar_codigo">Solicitar Código</button>
                        </div>
                        <div class="login-link">
                            <a href="login.php"><i class="fas fa-arrow-left"></i> Voltar para o Login</a>
                        </div>
                    </form>
                <?php elseif ($etapa === 2): ?>
                    <!-- Etapa 2: Verificar código -->
                    <div class="step-description">
                        <p>Digite o código de 6 dígitos enviado para seu e-mail.</p>
                        <p><small>E-mail: <?php echo htmlspecialchars($_SESSION["reset_email"] ?? ''); ?></small></p>
                    </div>
                    
                    <form method="post" action="recuperar_senha.php?etapa=2">
                        <div class="row">
                            <i class="fa-solid fa-key"></i>
                            <input type="text" name="codigo" id="codigo" placeholder="Código de verificação" maxlength="6" required>
                        </div>
                        <div class="row button">
                            <button type="submit" name="verificar_codigo">Verificar Código</button>
                        </div>
                        <div class="login-link">
                            <a href="recuperar_senha.php"><i class="fas fa-arrow-left"></i> Voltar</a>
                        </div>
                    </form>
                <?php elseif ($etapa === 3): ?>
                    <!-- Etapa 3: Definir nova senha -->
                    <div class="step-description">
                        <p>Defina sua nova senha de acesso.</p>
                    </div>
                    
                    <form method="post" action="recuperar_senha.php?etapa=3">
                        <div class="row">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="nova_senha" id="nova_senha" placeholder="Nova Senha" required>
                            <i class="fa-solid fa-eye toggle-password" id="toggle-nova-senha"></i>
                        </div>
                        <div class="row">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="confirmar_senha" id="confirmar_senha" placeholder="Confirmar Nova Senha" required>
                            <i class="fa-solid fa-eye toggle-password" id="toggle-confirmar-senha"></i>
                        </div>
                        <div class="row button">
                            <button type="submit" name="redefinir_senha">Redefinir Senha</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle de senha
            const toggles = document.querySelectorAll('.toggle-password');
            toggles.forEach(function(toggle) {
                toggle.addEventListener('click', function() {
                    const targetId = this.id.replace('toggle-', '');
                    const input = document.getElementById(targetId);
                    
                    if (input.type === 'password') {
                        input.type = 'text';
                        this.classList.remove('fa-eye');
                        this.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        this.classList.remove('fa-eye-slash');
                        this.classList.add('fa-eye');
                    }
                });
            });
            
            // Formatação do código de verificação
            const codigoInput = document.getElementById('codigo');
            if (codigoInput) {
                codigoInput.addEventListener('input', function(e) {
                    // Remover caracteres não numéricos
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    // Limitar a 6 dígitos
                    if (this.value.length > 6) {
                        this.value = this.value.slice(0, 6);
                    }
                });
            }
        });
    </script>
</body>
</html>
