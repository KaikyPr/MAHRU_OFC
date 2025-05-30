<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// **NOVO: Verificar nível de permissão para ACESSAR a página**
$nivel_usuario_logado = $_SESSION["usuario_nivel"] ?? 'visitante';
if ($nivel_usuario_logado !== 'admin' && $nivel_usuario_logado !== 'usuario') {
    $_SESSION['error_message'] = "Você não tem permissão para editar hacks.";
    header("Location: index.php"); // Redireciona para a lista de hacks
    exit;
}

// Conexão com o banco de dados
require_once("db/conexao.php"); // Usar require_once

// Verificar se o ID do hack foi fornecido
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    $_SESSION['error_message'] = "ID do hack inválido.";
    header("Location: index.php");
    exit;
}

$hack_id = (int)$_GET["id"];

// Mensagens de erro/sucesso
$mensagem = "";
$tipo_mensagem = ""; // success, error

// Buscar dados do hack para preencher o formulário
try {
    $sql_fetch = "SELECT * FROM hacks WHERE id = :id";
    $stmt_fetch = $pdo->prepare($sql_fetch);
    $stmt_fetch->bindParam(":id", $hack_id, PDO::PARAM_INT);
    $stmt_fetch->execute();
    $hack = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

    if (!$hack) {
        $_SESSION["error_message"] = "Hack não encontrado!";
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION["error_message"] = "Erro ao buscar dados do hack.";
    error_log("Erro PDO ao buscar hack para edição: " . $e->getMessage());
    header("Location: index.php");
    exit;
}

// Processar formulário de edição
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // **NOVO: Re-verificar permissão ANTES de processar o POST**
    if ($nivel_usuario_logado !== 'admin' && $nivel_usuario_logado !== 'usuario') {
        $mensagem = "Ação não permitida para seu nível de usuário.";
        $tipo_mensagem = "error";
    } elseif (!isset($_POST["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {
        // Validar token CSRF
        $mensagem = "Erro de validação CSRF. Tente novamente.";
        $tipo_mensagem = "error";
    } else {
        $nome = filter_input(INPUT_POST, "nome", FILTER_SANITIZE_SPECIAL_CHARS);
        $descricao = filter_input(INPUT_POST, "descricao", FILTER_SANITIZE_SPECIAL_CHARS);
        $piso = filter_input(INPUT_POST, "piso", FILTER_VALIDATE_INT);
        $tipo = filter_input(INPUT_POST, "tipo", FILTER_SANITIZE_SPECIAL_CHARS);
        $latitude = filter_input(INPUT_POST, "latitude", FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        $longitude = filter_input(INPUT_POST, "longitude", FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        $status = filter_input(INPUT_POST, "status", FILTER_SANITIZE_SPECIAL_CHARS);
        $remover_imagem = isset($_POST["remover_imagem"]) ? 1 : 0;

        // Validar dados
        if (empty($nome) || empty($descricao) || $piso === false || empty($tipo) || empty($status)) {
            $mensagem = "Os campos Nome, Descrição, Piso, Tipo e Status são obrigatórios!";
            $tipo_mensagem = "error";
        } elseif (!in_array($piso, [1, 2, 3])) {
            $mensagem = "Piso inválido!";
            $tipo_mensagem = "error";
        } elseif (!in_array($tipo, ["interno", "externo"])) {
            $mensagem = "Tipo inválido!";
            $tipo_mensagem = "error";
        } elseif (!in_array($status, ["ativo", "inativo", "manutencao"])) {
            $mensagem = "Status inválido!";
            $tipo_mensagem = "error";
        } elseif (($latitude !== null && $longitude === null) || ($latitude === null && $longitude !== null)) {
            $mensagem = "Se fornecer Latitude, deve fornecer Longitude também, e vice-versa.";
            $tipo_mensagem = "error";
        } else {
            // Processar upload de nova imagem ou remoção da existente
            $caminho_imagem_atual = $hack["imagem"];
            $caminho_imagem_final = $caminho_imagem_atual; // Assume que a imagem atual será mantida
            $erro_upload = null;

            // 1. Remover imagem existente?
            if ($remover_imagem && $caminho_imagem_atual) {
                if (file_exists($caminho_imagem_atual)) {
                    @unlink($caminho_imagem_atual);
                }
                $caminho_imagem_final = null; // Define como null se removida
            }

            // 2. Nova imagem enviada?
            if (isset($_FILES["imagem"]) && $_FILES["imagem"]["error"] === UPLOAD_ERR_OK) {
                $imagem_tmp = $_FILES["imagem"]["tmp_name"];
                $imagem_size = $_FILES["imagem"]["size"];
                $imagem_nome_original = basename($_FILES["imagem"]["name"]);
                $imagem_ext = strtolower(pathinfo($imagem_nome_original, PATHINFO_EXTENSION));
                $extensoes_permitidas = ["jpg", "jpeg", "png", "gif"];
                $tamanho_maximo_bytes = 5 * 1024 * 1024; // 5MB

                if (!in_array($imagem_ext, $extensoes_permitidas)) {
                    $erro_upload = "Erro: Tipo de arquivo inválido (Permitidos: JPG, JPEG, PNG, GIF).";
                } elseif ($imagem_size > $tamanho_maximo_bytes) {
                    $erro_upload = "Erro: Arquivo muito grande (Máximo: 5MB).";
                } else {
                    $diretorio_imagem = "uploads/hacks/";
                    if (!is_dir($diretorio_imagem)) {
                        if (!mkdir($diretorio_imagem, 0755, true)) {
                            $erro_upload = "Erro crítico: Não foi possível criar diretório de uploads.";
                            error_log("Falha ao criar diretório: " . $diretorio_imagem);
                        }
                    }
                     if ($erro_upload === null && !is_writable($diretorio_imagem)) {
                         $erro_upload = "Erro crítico: Sem permissão para salvar no diretório de uploads.";
                         error_log("Diretório sem permissão de escrita: " . $diretorio_imagem);
                    }
                    if ($erro_upload === null) {
                        $novo_nome = uniqid("hack_", true) . "." . $imagem_ext;
                        $caminho_destino = $diretorio_imagem . $novo_nome;
                        if (move_uploaded_file($imagem_tmp, $caminho_destino)) {
                            // Remover imagem antiga se uma nova foi enviada com sucesso
                            if ($caminho_imagem_atual && file_exists($caminho_imagem_atual)) {
                                @unlink($caminho_imagem_atual);
                            }
                            $caminho_imagem_final = $caminho_destino; // Define o novo caminho
                        } else {
                            $erro_upload = "Erro ao mover novo arquivo para destino final.";
                            error_log("Falha no move_uploaded_file para: " . $caminho_destino . " a partir de " . $imagem_tmp);
                        }
                    }
                }
                // Se houve erro no upload, definir mensagem
                if ($erro_upload !== null) {
                    $mensagem = $erro_upload;
                    $tipo_mensagem = "error";
                }
            } elseif (isset($_FILES["imagem"]) && $_FILES["imagem"]["error"] !== UPLOAD_ERR_NO_FILE) {
                 // Tratar outros erros de upload
                 switch ($_FILES["imagem"]["error"]) {
                     case UPLOAD_ERR_INI_SIZE: case UPLOAD_ERR_FORM_SIZE: $erro_upload = "Erro: O arquivo enviado excede o limite de tamanho."; break;
                     case UPLOAD_ERR_PARTIAL: $erro_upload = "Erro: O upload foi feito parcialmente."; break;
                     case UPLOAD_ERR_NO_TMP_DIR: $erro_upload = "Erro: Falta pasta temporária no servidor."; break;
                     case UPLOAD_ERR_CANT_WRITE: $erro_upload = "Erro: Falha ao escrever arquivo no disco."; break;
                     case UPLOAD_ERR_EXTENSION: $erro_upload = "Erro: Extensão PHP impediu o upload."; break;
                     default: $erro_upload = "Erro desconhecido no upload (Código: {".$_FILES["imagem"]["error"] ."})."; break;
                 }
                 $mensagem = $erro_upload;
                 $tipo_mensagem = "error";
            }

            // Somente atualiza se não houve erro de validação ou upload
            if ($tipo_mensagem !== "error") {
                try {
                    // Usar NULL explicitamente se latitude/longitude não forem fornecidos
                    $latitude_db = ($latitude !== null) ? $latitude : null;
                    $longitude_db = ($longitude !== null) ? $longitude : null;

                    $sql_update = "UPDATE hacks SET nome = :nome, descricao = :descricao, piso = :piso, tipo = :tipo, imagem = :imagem, latitude = :latitude, longitude = :longitude, status = :status WHERE id = :id";
                    $stmt_update = $pdo->prepare($sql_update);
                    $stmt_update->bindParam(":nome", $nome, PDO::PARAM_STR);
                    $stmt_update->bindParam(":descricao", $descricao, PDO::PARAM_STR);
                    $stmt_update->bindParam(":piso", $piso, PDO::PARAM_INT);
                    $stmt_update->bindParam(":tipo", $tipo, PDO::PARAM_STR);
                    $stmt_update->bindParam(":imagem", $caminho_imagem_final, PDO::PARAM_STR);
                    $stmt_update->bindParam(":latitude", $latitude_db, PDO::PARAM_STR);
                    $stmt_update->bindParam(":longitude", $longitude_db, PDO::PARAM_STR);
                    $stmt_update->bindParam(":status", $status, PDO::PARAM_STR);
                    $stmt_update->bindParam(":id", $hack_id, PDO::PARAM_INT);

                    if ($stmt_update->execute()) {
                        // Usar SESSÃO para mensagem de sucesso e redirecionar
                        $_SESSION["success_message"] = "Hack atualizado com sucesso!";
                        header("Location: index.php"); // Redireciona para a lista
                        exit;
                    } else {
                        $errorInfo = $stmt_update->errorInfo();
                        $mensagem = "Erro ao atualizar hack no banco de dados! Detalhes: " . ($errorInfo[2] ?? "N/A");
                        $tipo_mensagem = "error";
                        error_log("Erro PDO ao atualizar hack: " . print_r($errorInfo, true));
                    }
                } catch (PDOException $e) {
                    $mensagem = "Erro no banco de dados ao atualizar hack. Detalhes: " . $e->getMessage();
                    $tipo_mensagem = "error";
                    error_log("Exceção PDO ao atualizar hack: " . $e->getMessage());
                }
            }
        }
    }
    // Regenerar token CSRF após o POST
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
} else {
    // Gerar token CSRF inicial para o GET
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
}

// Define o título da página ANTES de incluir o header
$page_title = "Editar Hack - MAHRU";

// Inclui o cabeçalho padrão
include("includes/header.php");
?>

<!-- Conteúdo Principal da Página -->
<div class="main-content">
    <section class="section">
        <div class="section-header animate__animated animate__fadeInDown">
            <h1>Editar Hack: <?php echo htmlspecialchars($hack["nome"] ?? 'ID ' . $hack_id); ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item"><a href="painel.php">Visão Geral</a></div>
                <div class="breadcrumb-item"><a href="index.php">Visualizar Hacks</a></div>
                <div class="breadcrumb-item active">Editar Hack</div>
            </div>
        </div>

        <div class="section-body">
            <?php if (!empty($mensagem)): ?>
                <div class="alert <?php echo $tipo_mensagem === "success" ? "alert-success" : "alert-danger"; ?> animate__animated animate__fadeIn">
                    <?php echo htmlspecialchars($mensagem); ?>
                </div>
            <?php endif; ?>

            <div class="form-container animate__animated animate__fadeInUp">
                <h2 class="form-title">Informações do Hack</h2>

                <form action="editar_hack.php?id=<?php echo $hack_id; ?>" method="POST" enctype="multipart/form-data" id="form-edit-hack">
                    <!-- Campo CSRF -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="nome" class="form-label">Nome do Hack:</label>
                                <input type="text" name="nome" id="nome" class="form-control" value="<?php echo htmlspecialchars($hack["nome"]); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="piso" class="form-label">Piso:</label>
                                <select name="piso" id="piso" class="form-select" required>
                                    <option value="1" <?php echo ($hack["piso"] == 1) ? "selected" : ""; ?>>Piso 1</option>
                                    <option value="2" <?php echo ($hack["piso"] == 2) ? "selected" : ""; ?>>Piso 2</option>
                                    <option value="3" <?php echo ($hack["piso"] == 3) ? "selected" : ""; ?>>Piso 3</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="descricao" class="form-label">Descrição:</label>
                        <textarea name="descricao" id="descricao" class="form-control" rows="4" required><?php echo htmlspecialchars($hack["descricao"]); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label for="tipo" class="form-label">Tipo:</label>
                                <select name="tipo" id="tipo" class="form-select" required>
                                    <option value="interno" <?php echo (($hack["tipo"] ?? null) === "interno") ? "selected" : ""; ?>>Interno</option>
                                    <option value="externo" <?php echo (($hack["tipo"] ?? null) === "externo") ? "selected" : ""; ?>>Externo</option>
                                </select>
                            </div>
                        </div>
                         <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label for="status" class="form-label">Status:</label>
                                <select name="status" id="status" class="form-select" required>
                                    <option value="ativo" <?php echo (isset($hack["status"]) && $hack["status"] === "ativo") ? "selected" : ""; ?>>Ativo</option>
                                    <option value="inativo" <?php echo (isset($hack["status"]) && $hack["status"] === "inativo") ? "selected" : ""; ?>>Inativo</option>
                                    <option value="manutencao" <?php echo (isset($hack["status"]) && $hack["status"] === "manutencao") ? "selected" : ""; ?>>Em Manutenção</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label for="imagem" class="form-label">Nova Imagem (Opcional):</label>
                                <input type="file" name="imagem" id="imagem" class="form-control" accept="image/jpeg, image/png, image/gif">
                                <small class="form-text text-muted">Substituirá a atual. Max 5MB.</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                         <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="latitude" class="form-label">Latitude (Opcional):</label>
                                <input type="text" name="latitude" id="latitude" class="form-control" placeholder="Ex: -28.6789" value="<?php echo htmlspecialchars($hack["latitude"] ?? ""); ?>">
                                <small class="form-text text-muted">Para exibir no mapa.</small>
                            </div>
                        </div>
                         <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="longitude" class="form-label">Longitude (Opcional):</label>
                                <input type="text" name="longitude" id="longitude" class="form-control" placeholder="Ex: -49.3700" value="<?php echo htmlspecialchars($hack["longitude"] ?? ""); ?>">
                                 <small class="form-text text-muted">Para exibir no mapa.</small>
                           </div>
                        </div>
                    </div>

                    <div class="current-image-section my-4">
                        <h4 class="mb-3">Imagem Atual</h4>
                        <?php 
                        $imagem_exibicao = $hack["imagem"] ?? null;
                        $default_img = "img/img-sem-foto.jpg"; // Caminho relativo padrão
                        $imagem_valida = $imagem_exibicao && file_exists($imagem_exibicao);
                        $caminho_exibicao = $imagem_valida ? $imagem_exibicao : $default_img;
                        ?>
                        <div class="text-center">
                             <img id="current-img" src="<?php echo htmlspecialchars($caminho_exibicao); ?>" alt="Imagem Atual" style="max-width: 100%; max-height: 250px; border-radius: 10px; border: 1px solid #ddd;">
                        </div>
                        <?php if ($imagem_valida): // Só mostra opção de remover se existe imagem válida ?>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="remover_imagem" id="remover_imagem" value="1">
                            <label class="form-check-label" for="remover_imagem">
                                Remover imagem atual (não pode ser desfeito)
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="image-preview-container text-center my-4" style="display: none;">
                        <h4 class="mb-3">Pré-visualização da Nova Imagem</h4>
                        <div class="image-preview">
                            <img id="preview-img" src="#" alt="Preview" style="max-width: 100%; max-height: 250px; border-radius: 10px; border: 1px solid #ddd;">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php" class="btn btn-secondary btn-cancel btn-hover-effect mahru-link">
                            <i class="fas fa-arrow-left me-2"></i> Voltar
                        </a>
                        <button type="submit" class="btn btn-primary btn-submit btn-hover-effect">
                            <i class="fas fa-save me-2"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<?php
// Inclui o rodapé padrão
include("includes/footer.php");
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const imagemInput = document.getElementById('imagem');
    const previewContainer = document.querySelector('.image-preview-container');
    const previewImg = document.getElementById('preview-img');
    const currentImageSection = document.querySelector('.current-image-section');
    const removerImagemCheckbox = document.getElementById('remover_imagem');

    imagemInput.addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewContainer.style.display = 'block';
                // Opcional: esconder imagem atual quando preview é mostrado
                // currentImageSection.style.display = 'none'; 
            }
            reader.readAsDataURL(file);
        } else {
            previewContainer.style.display = 'none';
            previewImg.src = '#';
            // Opcional: mostrar imagem atual novamente se o input for limpo
            // currentImageSection.style.display = 'block'; 
        }
    });

    // Opcional: Lógica para esconder/mostrar preview/atual ao marcar/desmarcar remover
    if (removerImagemCheckbox) {
        removerImagemCheckbox.addEventListener('change', function() {
            if (this.checked) {
                // Se marcar para remover, talvez esconder a imagem atual?
                // document.getElementById('current-img').style.opacity = '0.5';
            } else {
                // document.getElementById('current-img').style.opacity = '1';
            }
        });
    }
});
</script>

