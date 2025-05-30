<?php
session_start();
require_once("db/conexao.php");

// Verificar se já está logado
if (isset($_SESSION["usuario_id"])) {
    header("Location: painel.php");
    exit;
}

// Mensagens de erro/sucesso
$login_mensagem = "";
$login_tipo_mensagem = "";

// Processar login
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["login_action"])) {
    if (isset($_POST["email"]) && isset($_POST["senha"])) {
        $email = filter_var($_POST["email"], FILTER_SANITIZE_EMAIL);
        $senha = $_POST["senha"];
        
        try {
            $sql = "SELECT * FROM usuarios WHERE email = :email";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verificar senha usando password_verify
                if (isset($usuario["senha"]) && password_verify($senha, $usuario["senha"])) {
                    // Senha correta
                    $_SESSION["usuario_id"] = $usuario["id"];
                    $_SESSION["usuario_nome"] = $usuario["nome"];
                    $_SESSION["usuario_nivel"] = $usuario["nivel"];
                    $_SESSION["usuario_avatar"] = isset($usuario["avatar"]) && !empty($usuario["avatar"]) ? $usuario["avatar"] : "img/img-sem-foto.jpg";
                    
                    header("Location: painel.php");
                    exit;
                } else {
                    // Senha incorreta ou hash inválido
                    $login_mensagem = "Email ou Senha incorreta!"; // Mensagem genérica por segurança
                    $login_tipo_mensagem = "error";
                }
            } else {
                $login_mensagem = "Usuário não encontrado!";
                $login_tipo_mensagem = "error";
            }
        } catch (PDOException $e) {
            $login_mensagem = "Erro no banco de dados: " . $e->getMessage();
            $login_tipo_mensagem = "error";
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
    <title>Login - MAHRU</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <style>
        /* Estilos gerais */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background-color: #121212; color: white; height: 100vh; display: flex; justify-content: center; align-items: center; background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('img/LOGO.png') center/cover; }
        .container-login { width: 100%; max-width: 400px; padding: 20px; }
        .wrapper-login { width: 100%; background-color: #2a2a2a; border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6); overflow: hidden; animation: fadeIn 0.5s ease; }
        .title { background-color: #00aaff; color: white; font-size: 30px; font-weight: 600; text-align: center; line-height: 100px; border-radius: 15px 15px 0 0; user-select: none; }
        .form-login { padding: 30px 25px 25px 25px; }
        .row { position: relative; margin-bottom: 25px; }
        .row > i:first-child { position: absolute; color: #00aaff; font-size: 20px; top: 50%; transform: translateY(-50%); left: 15px; }
        .toggle-password { position: absolute; cursor: pointer; color: #999; font-size: 18px; top: 50%; transform: translateY(-50%); right: 15px; z-index: 2; }
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%; height: 50px; padding: 0 20px 0 45px; border: 1px solid #444; outline: none; font-size: 16px; border-radius: 8px; background-color: #333; color: white; transition: all 0.3s ease;
        }
        input[type="password"] { padding-right: 45px; } /* Espaço para o ícone de olho */
        input:focus { border-color: #00aaff; box-shadow: 0 0 10px rgba(0, 170, 255, 0.3); }
        input::placeholder { color: #999; }
        .button input {
            width: 100%; height: 50px; background-color: #00aaff; border: none; color: white; font-size: 18px; font-weight: 500; letter-spacing: 1px; cursor: pointer; transition: all 0.3s ease; padding: 0; border-radius: 8px;
        }
        .button input:hover { background-color: #0088cc; transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2); }
        .back-link, .forgot-password-link {
            text-align: center; margin-top: 20px; font-size: 14px;
        }
        .back-link a, .forgot-password-link a {
            color: #00aaff; text-decoration: none; transition: all 0.3s ease;
        }
        .back-link a:hover, .forgot-password-link a:hover { text-decoration: underline; color: #0088cc; }
        .forgot-password-link { text-align: right; margin-bottom: 15px; margin-top: -10px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; text-align: center; font-weight: bold; }
        .alert-success { background-color: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }
        .alert-error { background-color: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid #dc3545; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="container-login">
        <div class="wrapper-login">
            <div class="title">
                <span>MAHRU - Login</span>
            </div>
            
            <!-- Formulário de Login -->
            <form method="post" action="login.php" class="form-login">
                <input type="hidden" name="login_action" value="1">
                <?php if (!empty($login_mensagem)): ?>
                    <div class="alert alert-<?php echo $login_tipo_mensagem; ?>">
                        <?php echo $login_mensagem; ?>
                    </div>
                <?php endif; ?>
                <div class="row">
                    <i class="fa-solid fa-user"></i>
                    <input type="email" name="email" placeholder="E-mail" required>
                </div>
                <div class="row">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" placeholder="Senha" name="senha" id="senha-login" required>
                    <i class="fa-solid fa-eye toggle-password" id="toggle-senha-login"></i>
                </div>
                <div class="forgot-password-link">
                    <a href="recuperar_senha.php">Esqueci minha senha</a>
                </div>
                <div class="row button">
                    <input type="submit" value="Acessar">
                </div>
                <div class="back-link">
                    <a href="index.html"><i class="fas fa-arrow-left"></i> Voltar para a página inicial</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle de senha
            const togglePassword = document.getElementById('toggle-senha-login');
            const passwordInput = document.getElementById('senha-login');
            
            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }
        });
    </script>
</body>
</html>
