<?php
/**
 * Script para atualizar automaticamente o banco de dados
 * Adiciona todas as colunas necessárias para o sistema de verificação por email
 */

// Iniciar sessão e incluir conexão com o banco
session_start();
require_once("db/conexao.php");

// Definir cabeçalhos para exibir mensagens em formato texto
header('Content-Type: text/plain; charset=utf-8');

echo "Iniciando atualização do banco de dados...\n\n";

try {
    // Verificar se a tabela usuarios existe
    $sql_check_table = "SHOW TABLES LIKE 'usuarios'";
    $result = $pdo->query($sql_check_table);
    
    if ($result->rowCount() == 0) {
        echo "ERRO: A tabela 'usuarios' não existe no banco de dados!\n";
        exit;
    }
    
    echo "Tabela 'usuarios' encontrada. Verificando colunas...\n";
    
    // Array com todas as colunas necessárias e seus tipos
    $colunas = [
        'email_change_code' => 'VARCHAR(10)',
        'email_change_expires' => 'DATETIME',
        'new_email_pending' => 'VARCHAR(255)',
        'new_email_code' => 'VARCHAR(10)',
        'new_email_code_expires' => 'DATETIME',
        'password_change_code' => 'VARCHAR(10)',
        'password_change_expires' => 'DATETIME',
        'reset_password_code' => 'VARCHAR(10)',
        'reset_password_expires' => 'DATETIME'
    ];
    
    // Verificar quais colunas já existem
    $sql_check_columns = "SHOW COLUMNS FROM usuarios";
    $result = $pdo->query($sql_check_columns);
    $existing_columns = [];
    
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[] = $row['Field'];
    }
    
    echo "Colunas existentes: " . implode(", ", $existing_columns) . "\n\n";
    echo "Adicionando colunas faltantes...\n";
    
    // Adicionar colunas faltantes
    $colunas_adicionadas = 0;
    
    foreach ($colunas as $coluna => $tipo) {
        if (!in_array($coluna, $existing_columns)) {
            $sql_add_column = "ALTER TABLE usuarios ADD COLUMN $coluna $tipo";
            $pdo->exec($sql_add_column);
            echo "- Coluna '$coluna' adicionada com sucesso ($tipo)\n";
            $colunas_adicionadas++;
        } else {
            echo "- Coluna '$coluna' já existe\n";
        }
    }
    
    if ($colunas_adicionadas > 0) {
        echo "\nForam adicionadas $colunas_adicionadas colunas ao banco de dados.\n";
    } else {
        echo "\nTodas as colunas necessárias já existem no banco de dados.\n";
    }
    
    echo "\nAtualização concluída com sucesso!\n";
    echo "Agora você pode usar todas as funcionalidades de verificação por email e recuperação de senha.\n";
    
} catch (PDOException $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
?>
