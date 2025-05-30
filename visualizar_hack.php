<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário está logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// Conexão com o banco de dados
require_once("db/conexao.php"); // Usar require_once

// Verifica se o ID do hack foi fornecido
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: index.php");
    exit;
}

$hack_id = (int)$_GET["id"];

// Busca os dados do hack
try {
    $sql = "SELECT h.*, u.nome as nome_usuario_criador FROM hacks h LEFT JOIN usuarios u ON h.usuario_id = u.id WHERE h.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":id", $hack_id);
    $stmt->execute();
    $hack = $stmt->fetch(PDO::FETCH_ASSOC);

    // Se o hack não existir, redireciona para a página inicial
    if (!$hack) {
        $_SESSION["error_message"] = "Hack não encontrado!";
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION["error_message"] = "Erro no banco de dados ao tentar visualizar o hack ID: " . $hack_id;
    error_log("Erro PDO ao buscar hack para visualização: " . $e->getMessage());
    header("Location: index.php");
    exit;
}

// Define o título da página ANTES de incluir o header
$page_title = "Detalhes do Hack - MAHRU";

// Inclui o cabeçalho padrão
include("includes/header.php");
?>

<!-- Conteúdo Principal da Página -->
<div class="main-content">
    <section class="section">
        <div class="section-header animate__animated animate__fadeInDown">
            <h1>Detalhes do Hack</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item"><a href="painel.php">Visão Geral</a></div>
                <div class="breadcrumb-item"><a href="index.php">Visualizar Hacks</a></div>
                <div class="breadcrumb-item active">Detalhes</div>
            </div>
        </div>

        <div class="section-body">
            <div class="hack-details-container animate__animated animate__fadeInUp">
                <div class="row">
                    <!-- Coluna da Imagem -->
                    <div class="col-lg-5">
                        <div class="hack-image-large-container">
                            <?php
                            // Define o caminho padrão relativo à web
                            $default_hack_image = "img/hack-default.jpg"; 
                            $imagem_hack = $default_hack_image; // Começa com o padrão
                            
                            // Verifica se há uma imagem definida no banco
                            if (!empty($hack["imagem"])) {
                                // Constrói o caminho completo no sistema de arquivos para verificação
                                // visualizar_hack.php está na raiz do projeto, então o caminho relativo do banco funciona diretamente aqui
                                $hack_image_filesystem_path = __DIR__ . '/' . $hack["imagem"];
                                
                                // Verifica se o arquivo existe e é legível no servidor
                                if (file_exists($hack_image_filesystem_path) && is_readable($hack_image_filesystem_path)) {
                                    // Se existe, usa o caminho relativo da web (do banco) para o src
                                    $imagem_hack = $hack["imagem"];
                                }
                                // else: mantém a imagem padrão ($imagem_hack já definida)
                            }
                            // else: mantém a imagem padrão se $hack["imagem"] estiver vazio ($imagem_hack já definida)
                            ?>
                            <img src="<?php echo htmlspecialchars($imagem_hack); ?>" alt="<?php echo htmlspecialchars($hack["nome"]); ?>" class="img-fluid rounded shadow-lg">
                        </div>
                    </div>

                    <!-- Coluna de Informações -->
                    <div class="col-lg-7">
                            <h2 class="hack-title"><?php echo htmlspecialchars($hack["nome"] ?? 'Nome Indisponível'); ?></h2>

                            <div class="hack-meta-details mb-3">
                                <span class="badge badge-piso">Piso <?php echo htmlspecialchars($hack["piso"] ?? '?'); ?></span>
                                <span class="badge <?php echo ($hack["tipo"] ?? '') === "interno" ? "badge-tipo" : "badge-tipo-externo"; ?>">
                                    <?php echo ucfirst(htmlspecialchars($hack["tipo"] ?? 'Desconhecido')); ?>
                                </span>
                                <span class="badge <?php
                                    $status_class = 'bg-light text-dark'; // Default class
                                    $status_text = 'Desconhecido'; // Default text
                                    if (isset($hack['status'])) {
                                        $status_text = ucfirst(htmlspecialchars($hack['status']));
                                        switch ($hack['status']) {
                                            case 'ativo': $status_class = 'bg-success'; break;
                                            case 'inativo': $status_class = 'bg-secondary'; break;
                                            case 'manutencao': $status_class = 'bg-warning text-dark'; break;
                                        }
                                    }
                                    echo $status_class;
                                ?>">
                                    <?php echo $status_text; ?>
                                </span>
                                <span class="hack-date ms-2"><i class="far fa-calendar-alt me-1"></i> Cadastrado em: <?php echo isset($hack["data_cadastro"]) ? date("d/m/Y", strtotime($hack["data_cadastro"])) : 'Data Desconhecida'; ?></span>
                            </div>

                            <div class="hack-description mb-4">
                                <h4>Descrição</h4>
                                <p><?php echo nl2br(htmlspecialchars($hack["descricao"] ?? 'Sem descrição.')); ?></p>
                            </div>

                            <?php if (!empty($hack["latitude"]) && !empty($hack["longitude"])): ?>
                            <div class="hack-location mb-4">
                                <h4>Localização (Coordenadas)</h4>
                                <p><i class="fas fa-map-marker-alt me-1"></i> Latitude: <?php echo htmlspecialchars($hack["latitude"]); ?>, Longitude: <?php echo htmlspecialchars($hack["longitude"]); ?></p>
                                <!-- Adicionar link para mapa externo, se desejado -->
                                <a href="https://www.google.com/maps?q=<?php echo htmlspecialchars($hack["latitude"]); ?>,<?php echo htmlspecialchars($hack["longitude"]); ?>" target="_blank" class="btn btn-sm btn-outline-primary mahru-link">
                                    <i class="fas fa-external-link-alt me-1"></i> Ver no Google Maps
                                </a>
                            </div>
                            <?php endif; ?>

                            <div class="hack-creator mb-4">
                                <h4>Cadastrado por</h4>
                                <p><i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($hack["nome_usuario_criador"] ?? "Usuário Desconhecido"); ?></p>
                            </div>

                            <div class="hack-actions mt-4">
                                <a href="index.php" class="btn-cancel btn-hover-effect mahru-link">
                                    <i class="fas fa-arrow-left me-2"></i> Voltar
                                </a>
                                <a href="editar_hack.php?id=<?php echo $hack_id; ?>" class="btn-edit btn-hover-effect mahru-link">
                                    <i class="fas fa-edit me-2"></i> Editar
                                </a>
                                <a href="excluir_hack.php?id=<?php echo $hack_id; ?>" class="btn-delete btn-hover-effect mahru-link">
                                    <i class="fas fa-trash me-2"></i> Excluir
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seção de Comentários (a ser implementada) -->
            <!--
            <div class="comments-section mt-5 animate__animated animate__fadeInUp">
                <h3>Comentários</h3>
                </div>
            -->

        </div>
    </section>
</div>

<?php
// Inclui o rodapé padrão
include("includes/footer.php");
?>

