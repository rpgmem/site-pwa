=== Site PWA ===

Transforma o site em um app instalável: um ícone na tela inicial, uma estrutura de app para quem está conectado e telas que outros plugins registram.

== Description ==

O Site PWA torna um site WordPress instalável no celular ou no computador, como um app com ícone e janela próprios. Quem está conectado abre a estrutura do app, e outros plugins acrescentam suas telas a ela por um contrato de registro.

Esta primeira versão é a base do plugin: ele se instala, se atualiza pelos releases do GitHub e ainda não guarda nada. O app em si chega nas versões seguintes.

== Installation ==

1. Baixe o `site-pwa-X.Y.Z.zip` do release mais recente no GitHub.
2. No WordPress, vá em Plugins → Adicionar novo → Enviar plugin, escolha o zip e ative.

== Privacidade ==

O plugin não guarda dados pessoais. Ele mantém um único cache do release mais recente no GitHub, em um transiente do site apagado junto com o plugin.

== Changelog ==

O histórico completo de cada versão está no [CHANGELOG.md](https://github.com/rpgmem/site-pwa/blob/main/CHANGELOG.md).

== Upgrade Notice ==

= 0.1.0 =
A base do plugin: ele se instala e se atualiza pelos releases do GitHub, com cada pacote conferido pelo SHA-256. O app chega nas próximas versões. Detalhes no CHANGELOG.md.
