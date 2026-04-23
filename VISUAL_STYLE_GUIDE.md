# Guia de Padrão Visual - Carvist

Este documento detalha o padrão visual da aplicação Carvist, servindo como referência para o desenvolvimento de novas interfaces e manutenção das existentes.

## 1. Princípios Gerais
A aplicação utiliza um design limpo, focado em produtividade e visualização de dados (tabelas e filtros). O layout é responsivo e suporta temas claro (Light) e escuro (Dark).

## 2. Paleta de Cores

### 2.1. Cores Base (Variáveis CSS)
| Variável | Descrição | Light Mode | Dark Mode |
| :--- | :--- | :--- | :--- |
| `--bg-color` | Cor de fundo da página | `#f4f7f6` | `#1a1a1a` |
| `--text-color` | Cor principal do texto | `#333333` | `#e0e0e0` |
| `--card-bg` | Fundo de cards/seções | `#ffffff` | `#2d2d2d` |
| `--border-color` | Cor de bordas | `#dddddd` | `#444444` |
| `--thead-bg` | Fundo do cabeçalho da tabela | `#2c3e50` | `#000000` |
| `--thead-text` | Texto do cabeçalho da tabela | `#ffffff` | `#ffffff` |
| `--row-even` | Fundo de linhas pares | `#f9f9f9` | `#333333` |
| `--row-selected` | Fundo de linha selecionada | `#e3f2fd` | `#1e3a5f` |

### 2.2. Cores de Ação e Status
| Tipo | Cor | Hexadecimal |
| :--- | :--- | :--- |
| **Primária** | Azul | `#3498db` |
| **Sucesso** | Verde | `#27ae60` |
| **Atenção/Erro** | Vermelho | `#e74c3c` |
| **Aviso** | Amarelo | `#f1c40f` |
| **Secundária** | Cinza | `#95a5a6` |
| **Destaque** | Laranja | `#e67e22` |

## 3. Tipografia
- **Família Principal:** `'Segoe UI', Tahoma, Geneva, Verdana, sans-serif`.
- **Tamanho Base:** `12px` (otimizado para densidade de dados).
- **Títulos:** Negrito, com espaçamento entre letras (`letter-spacing: 1px`).

## 4. Layout e Estrutura

### 4.1. Barra Superior (`top-bar`)
- **Altura:** Flexível (padding `10px 20px`).
- **Elementos:** Logo à esquerda (40x40px, border-radius 8px), título em caixa alta, e botão de alternância de tema à direita.
- **Sombra:** `0 2px 10px rgba(0, 0, 0, 0.2)`.
- **Posicionamento:** Fixa no topo (`sticky`).

### 4.2. Navegação (`nav`)
- Localizada logo abaixo da barra superior.
- Estilo de "pílulas" dentro de um container com fundo `--card-bg`.
- **Links (`nav-link`):**
  - Padding: `8px 20px`.
  - Border-radius: `6px`.
  - Estado Ativo: Fundo `--primary-color` e texto branco.

### 4.3. Containers
- **Padrão:** `max-width: 1600px`.
- **Wide:** `max-width: 100%` (usado em tabelas extensas).

## 5. Componentes de Interface

### 5.1. Botões (`btn`)
Todos os botões possuem `border-radius: 4px`, `font-weight: bold` e transição de opacidade no hover.

- **`.btn-primary`:** Fundo azul, texto branco.
- **`.btn-success`:** Fundo verde, texto branco.
- **`.btn-clear` / `.btn-danger`:** Fundo vermelho, texto branco.
- **`.btn-secondary`:** Fundo cinza, texto branco.
- **`.link-btn`:** Botões pequenos dentro de tabelas (ex: "ECV", "AVAL"), `font-size: 10px`.

### 5.2. Formulários e Filtros
- **Seção de Filtros:** Container com borda e sombra leve.
- **Grid de Filtros:** Layout em grid responsivo (`grid-template-columns: repeat(auto-fit, minmax(150px, 1fr))`).
- **Inputs/Selects:** Padding `8px`, borda `--border-color`, fundo `--card-bg`.

### 5.3. Tabelas
- **Estilo:** Bordas colapsadas, largura 100%.
- **Cabeçalho:** Fundo escuro, texto branco, fixo no topo.
- **Linhas:** Cores alternadas (zebra) e destaque azul ao selecionar/editar.
- **Inputs em Tabela:** Sem bordas ou com bordas sutis para edição inline.

### 5.4. Badges e Tarjas
- **`.status-badge`:** Pequenas etiquetas arredondadas para status (ex: "SIM/NÃO").
- **`.tarja-animada`:** Etiquetas com animação de pulso para estados críticos ou processamento.
- **`.banner`:** Alertas de sucesso ou erro no topo da página.

## 6. Feedback Visual
- **Loading Overlay:** Fundo semi-transparente com desfoque (`backdrop-filter: blur(1px)`) e spinner animado.
- **Animações:**
  - `pulse`: Pulsação suave de opacidade e escala.
  - `spin`: Rotação contínua para ícones de carregamento.

## 7. Padrão de Ícones
A aplicação utiliza principalmente texto e cores para diferenciação, mas integra bibliotecas externas como **Select2** para seleções múltiplas com busca.
