<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário está logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// **NOVO: Verificar nível de permissão para ACESSAR a página/ação**
$nivel_usuario_logado = $_SESSION["usuario_nivel"] ?? 'visitante';
if ($nivel_usuario_logado !== 'admin' && $nivel_usuario_logado !== 'usuario') {
    $_SESSION['error_message'] = "Você não tem permissão para excluir hacks.";
    header("Location: index.php"); // Redireciona para a lista de hacks
    exit;
}

// Conexão com o banco de dados
require_once("db/conexao.php"); // Usar require_once

// Verifica se o ID do hack foi fornecido
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    $_SESSION['error_message'] = "ID do hack inválido para exclusão.";
    header("Location: index.php");
    exit;
}

$hack_id = (int)$_GET["id"];

// Busca os dados do hack para exibição na confirmação
try {
    $sql_fetch = "SELECT id, nome, piso, tipo, imagem FROM hacks WHERE id = :id"; // Buscar apenas o necessário
    $stmt_fetch = $pdo->prepare($sql_fetch);
    $stmt_fetch->bindParam(":id", $hack_id, PDO::PARAM_INT);
    $stmt_fetch->execute();
    $hack = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

    // Se o hack não existir, redireciona para a página inicial
    if (!$hack) {
        $_SESSION["error_message"] = "Hack não encontrado para exclusão!";
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION["error_message"] = "Erro ao buscar dados do hack para exclusão.";
    error_log("Erro PDO ao buscar hack para exclusão: " . $e->getMessage());
    header("Location: index.php");
    exit;
}

// Processar exclusão se confirmada (via GET - idealmente deveria ser POST com CSRF)
// **MELHORIA FUTURA: Mudar para método POST com token CSRF para maior segurança.**
if (isset($_GET["confirm"]) && $_GET["confirm"] == 1) {
    
    // **NOVO: Re-verificar permissão ANTES de processar a exclusão**
    if ($nivel_usuario_logado !== 'admin' && $nivel_usuario_logado !== 'usuario') {
        $_SESSION['error_message'] = "Ação de exclusão não permitida para seu nível de usuário.";
        header("Location: index.php");
        exit;
    }

    try {
        // Excluir o hack do banco de dados
        $sql_delete = "DELETE FROM hacks WHERE id = :id";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->bindParam(":id", $hack_id, PDO::PARAM_INT);

        if ($stmt_delete->execute()) {
            // Remover a imagem física se existir
            if (!empty($hack["imagem"]) && file_exists($hack["imagem"])) {
                // Evitar remover imagens padrão se estiverem sendo usadas
                if (basename($hack["imagem"]) !== 'img-sem-foto.jpg') { 
                    @unlink($hack["imagem"]);
                }
            }

            // Definir mensagem de sucesso e redirecionar
            $_SESSION["success_message"] = "Hack \"" . htmlspecialchars($hack["nome"]) . "\" excluído com sucesso!";
            header("Location: index.php");
            exit;
        } else {
            $_SESSION["error_message"] = "Erro ao executar a exclusão do hack. Tente novamente.";
            error_log("Erro PDO ao excluir hack: " . print_r($stmt_delete->errorInfo(), true));
            header("Location: index.php"); // Redireciona em caso de erro na execução
            exit;
        }
    } catch (PDOException $e) {
        // Verificar erro de chave estrangeira (ex: comentários associados)
        if ($e->getCode() == '23000') { // Código SQLSTATE para violação de integridade
             $_SESSION["error_message"] = "Não é possível excluir este hack pois existem registros relacionados (ex: comentários). Remova os registros relacionados primeiro.";
        } else {
            $_SESSION["error_message"] = "Erro no banco de dados ao excluir hack.";
        }
        error_log("Exceção PDO ao excluir hack: " . $e->getMessage());
        header("Location: index.php"); // Redireciona em caso de exceção
        exit;
    }
}

// Define o título da página ANTES de incluir o header
$page_title = "Confirmar Exclusão - MAHRU";

// Inclui o cabeçalho padrão
include("includes/header.php");
?>

<!-- Conteúdo Principal da Página -->
<div class="main-content">
    <section class="section">
        <div class="section-header animate__animated animate__fadeInDown">
            <h1>Excluir Hack</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item"><a href="painel.php">Visão Geral</a></div>
                <div class="breadcrumb-item"><a href="index.php">Visualizar Hacks</a></div>
                <div class="breadcrumb-item"><a href="visualizar_hack.php?id=<?php echo $hack_id; ?>">Detalhes</a></div>
                <div class="breadcrumb-item active">Excluir</div>
            </div>
        </div>

        <div class="section-body">
            <div class="delete-confirmation-container card shadow-lg animate__animated animate__fadeInUp">
             <div class="card-body">
                <div class="row align-items-center">
                    <!-- Coluna da Imagem -->
                    <div class="col-lg-4 text-center">
                        <div class="hack-image-container mb-4 mb-lg-0">
                             <?php
                            $default_hack_image = "img/img-sem-foto.jpg"; 
                            $imagem_hack_excluir = $default_hack_image;
                            if (!empty($hack["imagem"])) {
                                $hack_image_filesystem_path = __DIR__ . '/' . $hack["imagem"];
                                if (file_exists($hack_image_filesystem_path) && is_readable($hack_image_filesystem_path)) {
                                    $imagem_hack_excluir = $hack["imagem"];
                                }
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($imagem_hack_excluir); ?>" alt="<?php echo htmlspecialchars($hack["nome"]); ?>" class="img-fluid rounded shadow-sm" style="max-height: 250px; max-width: 100%; object-fit: cover;">
                        </div>
                    </div>

                    <!-- Coluna de Confirmação -->
                    <div class="col-lg-8">
                        <div class="delete-confirmation-content text-center text-lg-start">
                            <div class="delete-icon text-danger mb-3">
                                <i class="fas fa-exclamation-triangle fa-3x"></i>
                            </div>

                            <h2 class="delete-title mb-3">Confirmar Exclusão</h2>

                            <div class="hack-info-summary mb-4">
                                <h3><?php echo htmlspecialchars($hack["nome"]); ?></h3>
                                <div class="hack-meta-summary mb-3">
                                    <span class="badge bg-secondary">Piso <?php echo htmlspecialchars($hack["piso"]); ?></span>
                                    <span class="badge <?php echo $hack["tipo"] === "interno" ? "bg-info" : "bg-warning"; ?>">
                                        <?php echo ucfirst(htmlspecialchars($hack["tipo"])); ?>
                                    </span>
                                </div>
                                <p class="delete-warning text-danger fw-bold">
                                    Você está prestes a excluir permanentemente este hack. Esta ação não pode ser desfeita.
                                </p>
                            </div>

                            <div class="delete-actions d-flex justify-content-center justify-content-lg-start gap-3">
                                <a href="visualizar_hack.php?id=<?php echo $hack_id; ?>" class="btn btn-secondary btn-lg btn-cancel btn-hover-effect mahru-link">
                                    <i class="fas fa-arrow-left me-2"></i> Cancelar
                                </a>
                                <a href="excluir_hack.php?id=<?php echo $hack_id; ?>&confirm=1" class="btn btn-danger btn-lg btn-hover-effect mahru-link">
                                    <i class="fas fa-trash me-2"></i> Confirmar Exclusão
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
              </div>
            </div>
        </div>
    </section>
</div>

<?php
// Inclui o rodapé padrão
include("includes/footer.php");
?>

