# Checkout MB WAY — SIBS SPG

O checkout envia a criação do pagamento para `checkout.php`; as credenciais SIBS ficam apenas no servidor. O backend cria a sessão SIBS, dispara o pedido MB WAY e `status.php` confirma o resultado antes de mostrar a página de agradecimento.

## Configuração no servidor

Defina estas variáveis no ambiente do PHP (não as coloque no JavaScript ou no Git):

```text
SIBS_API_BASE_URL=https://<url-do-ambiente-sibs>
SIBS_CLIENT_ID=<client-id>
SIBS_AUTH_TOKEN=<auth-token>
SIBS_TERMINAL_ID=<terminal-id>
SIBS_STORAGE_DIR=/home/acodes-406/private/sibs-transactions
```

`SIBS_CLIENT_ID` sozinho não é suficiente. A SIBS também exige o `AuthToken` e o `TerminalId`. Obtenha-os no Backoffice SIBS para o ambiente escolhido. Use primeiro a URL de sandbox fornecida pela SIBS e só depois a URL de produção.

O diretório configurado em `SIBS_STORAGE_DIR` precisa permitir escrita pelo processo PHP e deve ficar fora de `htdocs`. Ele guarda apenas o identificador da transação e metadados mínimos para impedir consultas arbitrárias. O checkout não inicia pagamentos enquanto essa configuração ou alguma credencial estiver ausente.

No servidor com PHP-FPM, coloque as variáveis no pool PHP-FPM do site (diretivas `env[NOME] = valor`) e reinicie o serviço. Se o site usa Apache com `mod_php`, coloque as diretivas `SetEnv NOME valor` na configuração do virtual host, fora de `htdocs`, e recarregue o Apache. A forma exata depende da configuração do alojamento. Não use `.env` ou `.htaccess` públicos para guardar o token.

## Publicação e validação

O alojamento precisa executar PHP com a extensão cURL e TLS 1.2+. Publique `checkout.php`, `status.php` e `sibs.php` junto com as páginas HTML. Faça um pagamento de teste no simulador SIBS e confirme que a aprovação no MB WAY leva à página `obrigado.html`.

Para produção, configure também a Merchant Notification/webhook no Backoffice SIBS para reconciliação. O polling do checkout é mantido para a experiência imediata do doador, mas a notificação é o mecanismo recomendado para o ciclo assíncrono.
