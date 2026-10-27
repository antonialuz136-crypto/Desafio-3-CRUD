
<?php
$conexao = new mysqli("localhost", "root", "", "loja");

if ($conexao->connect_error) {
    die("Erro na conexão: " . $conexao->connect_error);
}

$conexao->set_charset("utf8mb4");


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = $_POST["acao"] ?? "";

    if ($acao === "salvar") {
        $id = (int)($_POST["id"] ?? 0);
        $nome = trim($_POST["nome"] ?? "");
        $categoria = trim($_POST["categoria"] ?? "");
        $precoTexto = $_POST["preco"] ?? "";
        $estoqueTexto = $_POST["estoque"] ?? "";
        $descricao = trim($_POST["descricao"] ?? "");

        if (
            $nome === "" ||
            $categoria === "" ||
            !is_numeric($precoTexto) ||
            (float)$precoTexto < 0 ||
            filter_var($estoqueTexto, FILTER_VALIDATE_INT) === false ||
            (int)$estoqueTexto < 0
        ) {
            die("Dados inválidos. <a href='index.php'>Voltar</a>");
        }

        $preco = (float)$precoTexto;
        $estoque = (int)$estoqueTexto;

        if ($id > 0) {
            $sql = "UPDATE produtos
                    SET nome=?, categoria=?, preco=?,
                        estoque=?, descricao=?
                    WHERE id=?";

            $stmt = $conexao->prepare($sql);
            $stmt->bind_param(
                "ssdisi",
                $nome,
                $categoria,
                $preco,
                $estoque,
                $descricao,
                $id
            );
        } else {
            $sql = "INSERT INTO produtos
                    (nome, categoria, preco, estoque, descricao)
                    VALUES (?, ?, ?, ?, ?)";

            $stmt = $conexao->prepare($sql);
            $stmt->bind_param(
                "ssdis",
                $nome,
                $categoria,
                $preco,
                $estoque,
                $descricao
            );
        }

        $stmt->execute();

        header("Location: index.php");
        exit;
    }

    if ($acao === "excluir") {
        $id = (int)($_POST["id"] ?? 0);

        $stmt = $conexao->prepare(
            "DELETE FROM produtos WHERE id=?"
        );

        $stmt->bind_param("i", $id);
        $stmt->execute();

        header("Location: index.php");
        exit;
    }
}


$busca = trim($_GET["busca"] ?? "");

if ($busca !== "") {
    $stmt = $conexao->prepare(
        "SELECT * FROM produtos
         WHERE nome LIKE ?
         ORDER BY id DESC"
    );

    $termo = "%" . $busca . "%";
    $stmt->bind_param("s", $termo);
    $stmt->execute();

    $produtos = $stmt->get_result();
} else {
    $produtos = $conexao->query(
        "SELECT * FROM produtos ORDER BY id DESC"
    );
}


$editar = null;

if (isset($_GET["editar"])) {
    $idEditar = (int)$_GET["editar"];

    $stmt = $conexao->prepare(
        "SELECT * FROM produtos WHERE id=?"
    );

    $stmt->bind_param("i", $idEditar);
    $stmt->execute();

    $editar = $stmt->get_result()->fetch_assoc();
}


function e($valor) {
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Produtos</title>
</head>

<body>

    <h1>Cadastro de Produtos</h1>

    <h2>
        <?= $editar ? "Editar produto" : "Cadastrar produto" ?>
    </h2>

    <form method="POST">
        <input type="hidden" name="acao" value="salvar">

        <input
            type="hidden"
            name="id"
            value="<?= e($editar["id"] ?? 0) ?>"
        >

        <label>Nome:</label><br>
        <input
            type="text"
            name="nome"
            required
            value="<?= e($editar["nome"] ?? "") ?>"
        ><br><br>

        <label>Categoria:</label><br>
        <input
            type="text"
            name="categoria"
            required
            value="<?= e($editar["categoria"] ?? "") ?>"
        ><br><br>

        <label>Preço (R$):</label><br>
        <input
            type="number"
            name="preco"
            min="0"
            step="0.01"
            required
            value="<?= e($editar["preco"] ?? "") ?>"
        ><br><br>

        <label>Estoque:</label><br>
        <input
            type="number"
            name="estoque"
            min="0"
            step="1"
            required
            value="<?= e($editar["estoque"] ?? "") ?>"
        ><br><br>

        <label>Descrição:</label><br>
        <textarea name="descricao"><?= e($editar["descricao"] ?? "") ?></textarea>
        <br><br>

        <button type="submit">Salvar produto</button>

        <?php if ($editar): ?>
            <a href="index.php">Cancelar edição</a>
        <?php endif; ?>
    </form>

    <hr>

    <h2>Produtos cadastrados</h2>

    <form method="GET">
        <input
            type="text"
            name="busca"
            placeholder="Pesquisar produto pelo nome"
            value="<?= e($busca) ?>"
        >

        <button type="submit">Pesquisar</button>
        <a href="index.php">Limpar pesquisa</a>
    </form>

    <br>

    <table border="1" cellpadding="8">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Categoria</th>
                <th>Preço</th>
                <th>Estoque</th>
                <th>Descrição</th>
                <th>Ações</th>
            </tr>
        </thead>

        <tbody>
            <?php while ($p = $produtos->fetch_assoc()): ?>
                <tr>
                    <td><?= e($p["nome"]) ?></td>

                    <td><?= e($p["categoria"]) ?></td>

                    <td>
                        R$ <?= number_format(
                            (float)$p["preco"],
                            2,
                            ",",
                            "."
                        ) ?>
                    </td>

                    <td>
                        <?= e($p["estoque"]) ?>

                        <?php if ((int)$p["estoque"] <= 5): ?>
                            <strong> - Estoque baixo!</strong>
                        <?php endif; ?>
                    </td>

                    <td><?= e($p["descricao"]) ?></td>

                    <td>
                        <a href="?editar=<?= (int)$p["id"] ?>">
                            Editar
                        </a>

                        <form method="POST"
                              onsubmit="return confirm('Deseja excluir este produto?')">

                            <input
                                type="hidden"
                                name="acao"
                                value="excluir"
                            >

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int)$p["id"] ?>"
                            >

                            <button type="submit">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</body>
</html>
