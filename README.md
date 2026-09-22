# Checkout MB WAY — SIBS SPG

O checkout envia a criação do pagamento para `checkout.php`; as credenciais SIBS ficam apenas no servidor. O backend cria a sessão SIBS, dispara o pedido MB WAY e `status.php` confirma o resultado antes de mostrar a página de agradecimento.

## Configuração no servidor

Na VPS, crie `/home/acodes-406/private/sibs.env`, fora de `htdocs`, com estas linhas:

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

## Publicação e validação

O alojamento precisa executar PHP com a extensão cURL e TLS 1.2+. Publique `checkout.php`, `status.php` e `sibs.php` junto com as páginas HTML. Faça um pagamento de teste no simulador SIBS e confirme que a aprovação no MB WAY leva à página `obrigado.html`.

Para produção, configure também a Merchant Notification/webhook no Backoffice SIBS para reconciliação. O polling do checkout é mantido para a experiência imediata do doador, mas a notificação é o mecanismo recomendado para o ciclo assíncrono.
