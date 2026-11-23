=== Site PWA ===

Transforma o site em um app instalável: um ícone na tela inicial, uma estrutura de app para quem está conectado e telas que outros plugins registram.

== Description ==

O Site PWA torna um site WordPress instalável no celular ou no computador, como um app com ícone e janela próprios. Quem está conectado abre a estrutura do app, e outros plugins acrescentam suas telas a ela por um contrato de registro.

Configurações → Site PWA liga o app; até lá ele fica desligado. O app é para usuários conectados: abre na sua própria estrutura, independente do tema, com a tela inicial e a conta, e o login do WordPress ganha a aparência do app dentro do app instalado. Um post ou página abre dentro do app em `/app/?p=ID`; Compartilhar e Copiar link entregam o endereço normal no site, e uma página feita com construtor de páginas ou modelo de página abre no site. A cor de destaque e os cantos do app vêm das próprias configurações ou, em Aparência, do design de outro plugin: o Design Público do Free Form Certificate já vem incluído. Claro ou escuro segue o Modo Escuro do Free Form Certificate por padrão, para o cabeçalho e o menu do app combinarem com as telas dentro deles; também pode ser sempre claro, sempre escuro ou seguir o aparelho. Outros plugins adicionam suas telas ao app pela action `site_pwa_register_screens`: a navegação inferior tem Início, até três delas e Mais, e cada tela aparece só para quem pode abri-la. No celular, a tela inicial do app convida para instalá-lo (o botão do próprio navegador no Android, os dois passos no iPhone), e os visitantes veem a faixa "Use o app" no site; nenhum dos dois aparece no computador ou dentro do app instalado. A tela inicial mostra os três posts mais recentes do site e as páginas escolhidas nas configurações, e Mais → Notícias lista todos os posts; as configurações escolhem as categorias das notícias e se cada página abre no site. O app fica em `/app/` (alterável pelo filtro `site_pwa_app_prefix`), com o ícone do site de Configurações → Geral, ou o do plugin, na tela inicial. Sem conexão, o app mostra uma página offline. As telas do app chegam nas versões seguintes.

== Installation ==

1. Baixe o `site-pwa-X.Y.Z.zip` do release mais recente no GitHub.
2. No WordPress, vá em Plugins → Adicionar novo → Enviar plugin, escolha o zip e ative.

== Screenshots ==

1. A tela inicial do app, com as telas que outros plugins adicionaram.
2. A tela da conta, aberta por Mais: quem está conectado, editar perfil, sair.
3. O login do WordPress dentro do app instalado.
4. A tela da conta no tema escuro.
5. Um post aberto dentro do app, com Copiar link e Abrir no site.
6. A tela da conta com a cor de destaque e os cantos do Design Público do Free Form Certificate.
7. Mais: as telas que não cabem na navegação inferior, e depois a conta.
8. Uma tela registrada por outro plugin, desenhada dentro do app.
9. O convite para instalar no Android: o botão de instalação do próprio navegador.
10. O convite para instalar no iPhone: os dois passos no Safari.
11. A faixa "Use o app" nas páginas do site, no celular.
12. A tela inicial com as últimas notícias e as páginas escolhidas.
13. A tela de notícias, dez posts por página.

== Outros service workers ==

O navegador mantém um service worker por raiz de site, então o do app e o de outro plugin (OneSignal, SuperPWA, PWA for WP, o plugin PWA) se substituiriam a cada visita. Três desfechos:

* **Nenhum outro worker:** basta ligar o app.
* **Outro plugin registra um:** Configurações → Site PWA o nomeia e o app fica desligado. Desative esse plugin ou confirme a substituição, que interrompe o worker dele. A tela também lista os workers que o seu próprio navegador já tem para o site.
* **Um serviço de push que precisa continuar funcionando:** ele pode dividir a raiz. O script dele, neste site, é carregado primeiro pelo worker do app quando devolvido pelo filtro `site_pwa_sw_imports`; um serviço de push também pode carregar o worker do app dentro do seu com `importScripts( '/site-pwa-sw.js' )`.

== Privacidade ==

O plugin não guarda dados pessoais. Ele mantém as próprias configurações, a revisão das suas regras de URL e um cache do release mais recente no GitHub, tudo apagado junto com o plugin. No navegador, o service worker do app guarda em cache só a página offline e a folha de estilo do app: nunca uma página, então nada do que um usuário conectado vê fica no aparelho.

== Changelog ==

O histórico completo de cada versão está no [CHANGELOG.md](https://github.com/rpgmem/site-pwa/blob/main/CHANGELOG.md).

== Upgrade Notice ==

= 0.1.0 =
A base do plugin: ele se instala e se atualiza pelos releases do GitHub, com cada pacote conferido pelo SHA-256. O app chega nas próximas versões. Detalhes no CHANGELOG.md.
