=== Site PWA ===

Transforma o site em um app instalável: um ícone na tela inicial, uma estrutura de app para quem está conectado e telas que outros plugins registram.

== Description ==

O Site PWA torna um site WordPress instalável no celular ou no computador, como um app com ícone e janela próprios. Quem está conectado abre a estrutura do app, e outros plugins acrescentam suas telas a ela por um contrato de registro.

Configurações → Site PWA liga o app; até lá ele fica desligado. O app fica em `/app/` (alterável pelo filtro `site_pwa_app_prefix`), com o ícone do site de Configurações → Geral, ou o do plugin, na tela inicial. Sem conexão, o app mostra uma página offline. As telas do app chegam nas versões seguintes.

== Installation ==

1. Baixe o `site-pwa-X.Y.Z.zip` do release mais recente no GitHub.
2. No WordPress, vá em Plugins → Adicionar novo → Enviar plugin, escolha o zip e ative.

== Privacidade ==

O plugin não guarda dados pessoais. Ele mantém as próprias configurações, a revisão das suas regras de URL e um cache do release mais recente no GitHub, tudo apagado junto com o plugin. No navegador, o service worker do app guarda em cache só a página offline e a folha de estilo do app: nunca uma página, então nada do que um usuário conectado vê fica no aparelho.

== Changelog ==

O histórico completo de cada versão está no [CHANGELOG.md](https://github.com/rpgmem/site-pwa/blob/main/CHANGELOG.md).

== Upgrade Notice ==

= 0.1.0 =
A base do plugin: ele se instala e se atualiza pelos releases do GitHub, com cada pacote conferido pelo SHA-256. O app chega nas próximas versões. Detalhes no CHANGELOG.md.
