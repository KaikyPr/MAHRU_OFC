<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// Conexão com o banco de dados
require_once("db/conexao.php");

// Buscar hacks agrupados por piso
$hacks_por_piso = [];
try {
    // Buscar todos os hacks ordenados por piso e nome
    $sql = "SELECT id, nome, descricao, piso, tipo, imagem, latitude, longitude, status FROM hacks ORDER BY piso ASC, nome ASC";
    $stmt = $pdo->query($sql);
    $hacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar por piso
    foreach ($hacks as $hack) {
        $piso = $hack["piso"];
        if (!isset($hacks_por_piso[$piso])) {
            $hacks_por_piso[$piso] = [];
        }
        $hacks_por_piso[$piso][] = $hack;
    }
    // Ordenar as chaves (pisos) para garantir a ordem 1, 2, 3...
    ksort($hacks_por_piso);

} catch (PDOException $e) {
    $mensagem_erro = "Erro ao buscar dados dos hacks: " . $e->getMessage();
    error_log($mensagem_erro);
    // Pode definir uma mensagem para exibir na página
}

// Define o título da página ANTES de incluir o header
$page_title = "Relatório de Hacks por Piso - MAHRU";

// Inclui o cabeçalho padrão
include("includes/header.php");
?>

<!-- Estilos específicos para o relatório -->
<style>
    .report-container {
        background-color: #fff;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        margin-top: 20px;
    }
    .floor-section {
        margin-bottom: 40px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eee;
    }
    .floor-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }
    .floor-title {
        font-size: 1.8em;
        color: #6777ef;
        margin-bottom: 25px;
        border-bottom: 2px solid #6777ef;
        padding-bottom: 10px;
        display: inline-block;
    }
    .hack-card {
        background-color: #f9f9f9;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        display: flex;
        align-items: center; /* Alinha ícone e texto verticalmente */
    }
    .hack-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    }
    .hack-icon {
        font-size: 2.5em; /* Tamanho do ícone */
        margin-right: 20px;
        color: #6c757d; /* Cor cinza padrão */
        width: 50px; /* Largura fixa para alinhamento */
        text-align: center;
    }
    .hack-icon.interno { color: #17a2b8; } /* Azul claro para interno */
    .hack-icon.externo { color: #ffc107; } /* Amarelo para externo */
    .hack-details h5 {
        margin-bottom: 5px;
        color: #34395e;
        font-weight: 600;
    }
    .hack-details p {
        margin-bottom: 8px;
        color: #555;
        font-size: 0.95em;
        line-height: 1.5;
    }
    .hack-details .badge {
        font-size: 0.8em;
        padding: 5px 10px;
    }
    .status-ativo { background-color: #28a745; color: white; }
    .status-inativo { background-color: #dc3545; color: white; }
    .status-manutencao { background-color: #ffc107; color: #333; }
    .no-hacks-message {
        font-style: italic;
        color: #888;
    }
</style>

<!-- Conteúdo Principal da Página -->
<div class="main-content">
    <section class="section">
        <div class="section-header animate__animated animate__fadeInDown">
            <h1>Relatório de Hacks por Piso</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item"><a href="painel.php">Visão Geral</a></div>
                <div class="breadcrumb-item active">Relatório de Hacks</div>
            </div>
        </div>

        <div class="section-body">
            <div class="report-container animate__animated animate__fadeInUp">
                <?php if (isset($mensagem_erro)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($mensagem_erro); ?></div>
                <?php elseif (empty($hacks_por_piso)): ?>
                    <div class="alert alert-info">Nenhum hack cadastrado ainda.</div>
                <?php else: ?>
                    <?php foreach ($hacks_por_piso as $piso => $hacks_do_piso): ?>
                        <div class="floor-section">
                            <h3 class="floor-title">Piso <?php echo htmlspecialchars($piso); ?></h3>
                            <?php if (empty($hacks_do_piso)): ?>
                                <p class="no-hacks-message">Nenhum hack cadastrado neste piso.</p>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($hacks_do_piso as $hack): ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="hack-card">
                                                <div class="hack-icon <?php echo htmlspecialchars($hack["tipo"]); ?>">
                                                    <i class="fas fa-network-wired"></i> <!-- Ícone genérico -->
                                                </div>
                                                <div class="hack-details">
                                                    <h5><?php echo htmlspecialchars($hack["nome"]); ?></h5>
                                                    <p><?php echo nl2br(htmlspecialchars($hack["descricao"])); ?></p>
                                                    <p>
                                                        <strong>Tipo:</strong> <?php echo ucfirst(htmlspecialchars($hack["tipo"])); ?>
                                                        <span class="badge ms-2 status-<?php echo htmlspecialchars($hack["status"]); ?>">
                                                            <?php echo ucfirst(htmlspecialchars($hack["status"])); ?>
                                                        </span>
                                                    </p>
                                                    <?php if (!empty($hack["latitude"]) && !empty($hack["longitude"])): ?>
                                                        <small class="text-muted">Localização: <?php echo htmlspecialchars($hack["latitude"]); ?>, <?php echo htmlspecialchars($hack["longitude"]); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<?php
// Inclui o rodapé padrão
include("includes/footer.php");
?>

