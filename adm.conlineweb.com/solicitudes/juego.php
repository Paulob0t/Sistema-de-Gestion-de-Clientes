<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Juego de Memoria</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background-color: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: center;
            max-width: 600px;
            width: 100%;
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 18px;
            color: #555;
        }
        
        .game-board {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .card {
            aspect-ratio: 3/4;
            background-color: #3498db;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 0;
            transition: transform 0.3s, background-color 0.3s;
            transform-style: preserve-3d;
        }
        
        .card.flipped {
            background-color: #f9f9f9;
            font-size: 24px;
            transform: rotateY(180deg);
        }
        
        .card.matched {
            background-color: #2ecc71;
            cursor: default;
        }
        
        button {
            background-color: #3498db;
            border: none;
            border-radius: 5px;
            color: white;
            cursor: pointer;
            font-size: 16px;
            padding: 10px 20px;
            transition: background-color 0.3s;
        }
        
        button:hover {
            background-color: #2980b9;
        }
        
        .win-message {
            display: none;
            font-size: 24px;
            color: #2ecc71;
            margin-top: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Juego de Memoria</h1>
        <div class="stats">
            <div>Movimientos: <span id="movements">0</span></div>
            <div>Tiempo: <span id="timer">0</span>s</div>
        </div>
        <div class="game-board" id="game-board"></div>
        <button id="reset-btn">Reiniciar Juego</button>
        <div class="win-message" id="win-message">¡FelicidadesToñito! Has ganado.</div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const gameBoard = document.getElementById('game-board');
            const resetBtn = document.getElementById('reset-btn');
            const movementsElement = document.getElementById('movements');
            const timerElement = document.getElementById('timer');
            const winMessage = document.getElementById('win-message');
            
            let cards = [];
            let flippedCards = [];
            let movements = 0;
            let matchedPairs = 0;
            let timer = 0;
            let timerInterval;
            
            // Símbolos para las cartas (parejas)
            const cardSymbols = ['🍎', '🍌', '🍒', '🍓', '🍊', '🍐', '🥝', '🍇'];
            
            // Inicializar el juego
            function initGame() {
                clearInterval(timerInterval);
                movements = 0;
                matchedPairs = 0;
                timer = 0;
                flippedCards = [];
                movementsElement.textContent = movements;
                timerElement.textContent = timer;
                winMessage.style.display = 'none';
                
                // Crear parejas de cartas
                cards = [...cardSymbols, ...cardSymbols];
                
                // Barajar las cartas
                shuffleCards();
                
                // Limpiar el tablero
                gameBoard.innerHTML = '';
                
                // Crear las cartas en el tablero
                cards.forEach((symbol, index) => {
                    const card = document.createElement('div');
                    card.classList.add('card');
                    card.dataset.symbol = symbol;
                    card.dataset.index = index;
                    card.textContent = symbol;
                    card.addEventListener('click', flipCard);
                    gameBoard.appendChild(card);
                });
                
                // Iniciar temporizador
                startTimer();
            }
            
            // Barajar las cartas (algoritmo Fisher-Yates)
            function shuffleCards() {
                for (let i = cards.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [cards[i], cards[j]] = [cards[j], cards[i]];
                }
            }
            
            // Voltear una carta
            function flipCard() {
                const selectedCard = this;
                
                // No hacer nada si la carta ya está volteada o emparejada
                if (selectedCard.classList.contains('flipped') || selectedCard.classList.contains('matched')) {
                    return;
                }
                
                // No permitir voltear más de dos cartas a la vez
                if (flippedCards.length < 2) {
                    selectedCard.classList.add('flipped');
                    flippedCards.push(selectedCard);
                    
                    // Si tenemos dos cartas volteadas, comprobar si son pareja
                    if (flippedCards.length === 2) {
                        movements++;
                        movementsElement.textContent = movements;
                        
                        setTimeout(checkMatch, 700);
                    }
                }
            }
            
            // Comprobar si las cartas volteadas son pareja
            function checkMatch() {
                const [card1, card2] = flippedCards;
                
                if (card1.dataset.symbol === card2.dataset.symbol) {
                    // Son pareja
                    card1.classList.add('matched');
                    card2.classList.add('matched');
                    matchedPairs++;
                    
                    // Comprobar si el juego ha terminado
                    if (matchedPairs === cardSymbols.length) {
                        endGame();
                    }
                } else {
                    // No son pareja, volver a voltear
                    card1.classList.remove('flipped');
                    card2.classList.remove('flipped');
                }
                
                // Reiniciar el array de cartas volteadas
                flippedCards = [];
            }
            
            // Iniciar temporizador
            function startTimer() {
                clearInterval(timerInterval);
                timerInterval = setInterval(() => {
                    timer++;
                    timerElement.textContent = timer;
                }, 1000);
            }
            
            // Finalizar el juego
            function endGame() {
                clearInterval(timerInterval);
                winMessage.style.display = 'block';
            }
            
            // Reiniciar el juego
            resetBtn.addEventListener('click', initGame);
            
            // Iniciar el juego al cargar la página
            initGame();
        });
    </script>
</body>
</html>