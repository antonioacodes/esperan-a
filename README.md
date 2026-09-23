# Checkout MB WAY — SIBS SPG

O checkout envia a criação do pagamento para `checkout.php`; as credenciais SIBS ficam apenas no servidor. O backend cria a sessão SIBS, dispara o pedido MB WAY e `status.php` confirma o resultado antes de mostrar a página de agradecimento.

## Configuração no servidor

O arquivo `sibs.env.example` está versionado no GitHub sem segredos. Na VPS, copie-o para `/home/acodes-406/private/sibs.env`, fora de `htdocs`, e substitua os valores de exemplo:

```text
SIBS_API_BASE_URL=https://URL-DA-API-SIBS
SIBS_CLIENT_ID=SEU-CLIENT-ID
SIBS_AUTH_TOKEN=SEU-AUTH-TOKEN
SIBS_TERMINAL_ID=SEU-TERMINAL-ID
SIBS_STORAGE_DIR=/home/acodes-406/private/sibs-transactions
```

`SIBS_CLIENT_ID` sozinho não é suficiente. A SIBS também exige o `AuthToken` e o `TerminalId`. Obtenha-os no Backoffice SIBS para o ambiente escolhido. Use primeiro a URL de sandbox fornecida pela SIBS e só depois a URL de produção.

O usuário que executa o PHP deve ter permissão para ler `sibs.env` e escrever em `sibs-transactions`. Restrinja o acesso ao arquivo, por exemplo com proprietário igual ao usuário do PHP e permissão `600`. O diretório de transações também deve pertencer a esse usuário e ter permissão `700`. O checkout não inicia pagamentos enquanto alguma configuração estiver ausente.

O PHP lê o arquivo automaticamente no caminho acima, sem necessidade de configurar o pool PHP-FPM ou reiniciar o serviço. Se mudar o local do arquivo, defina a variável de ambiente `SIBS_ENV_FILE` com o caminho absoluto. Variáveis SIBS definidas no ambiente do PHP têm prioridade sobre o arquivo. Nunca coloque `sibs.env` ou um token em `htdocs` ou no Git.

Se a SIBS devolver `HTTP 401`, execute `php /home/acodes-406/htdocs/406.acodes.pro/scripts/diagnose-auth.php` na VPS. O comando consulta o status de uma transação inexistente e faz um POST com JSON vazio (`{}`) em `/payments`, sem dados obrigatórios para criar uma transação ou iniciar MB WAY. Mostra apenas os códigos HTTP, sem exibir credenciais. Também compara o POST inválido nos endpoints SPG v1 publicados pela SIBS para verificar se as credenciais foram emitidas para outra versão da API. `invalid_checkout: HTTP 400` sugere que a autenticação passou e a API rejeitou o corpo inválido; `HTTP 401` indica que a autenticação ainda falha. Opcionalmente, preencha `SIBS_CLIENT_SECRET` no arquivo privado para comparar a autenticação com e sem esse cabeçalho na consulta de status. O checkout continua usando os cabeçalhos documentados pela SIBS v2 até que o resultado indique o contrário.

## Publicação e validação

Para testar a interface sem preencher dados, abra `pagamento.html?amount=25&demo=1`. O botão fica disponível e mostra uma demonstração claramente identificada. Nesse modo, o navegador não chama `checkout.php`, não envia pedido à SIBS e não registra evento de checkout. Para testar um pagamento real no sandbox SIBS, abra a página sem `demo=1` e informe um telemóvel MB WAY de teste válido.

O alojamento precisa executar PHP com a extensão cURL e TLS 1.2+. Publique `checkout.php`, `status.php` e `sibs.php` junto com as páginas HTML. Faça um pagamento de teste no simulador SIBS e confirme que a aprovação no MB WAY leva à página `obrigado.html`.

Para produção, configure também a Merchant Notification/webhook no Backoffice SIBS para reconciliação. O polling do checkout é mantido para a experiência imediata do doador, mas a notificação é o mecanismo recomendado para o ciclo assíncrono.
