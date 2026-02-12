class AsteroidsGame {
    constructor() {
        this.canvas = document.getElementById('gameCanvas');
        this.ctx = this.canvas.getContext('2d');
        
        // Debug: Check if canvas was found
        if (!this.canvas) {
            console.error('Game canvas not found!');
            return;
        }
        console.log('Game canvas found:', this.canvas);
        
        // Responsive canvas sizing for desktop
        this.setupResponsiveCanvas();
        
        // Load images
        this.coinImage = new Image();
        this.coinImage.src = './assets/coin.png';
        this.explosionImage = new Image();
        this.explosionImage.src = './assets/explosion.png';
        
        // Explosion effects array
        this.explosions = [];
        
        // Game state
        this.lives = 5;
        this.round = 1;
        this.score = 0;
        this.gameActive = false; // Start inactive until Begin Mission is clicked
        this.gameStarted = false;
        this.gamePaused = false;
        this.currentGameId = null;
        this.currentRound = 1;
        this.roundScore = 0;
        this.gameOverSaved = false; // Track if game over was saved
        
        // Respawn delay system
        this.isRespawning = false;
        this.respawnTimer = 0;
        this.respawnDelay = 60; // 1 second at 60fps
        
        // Statistics tracking
        this.totalAsteroidsDestroyed = 0;
        this.roundAsteroidsDestroyed = 0;
        this.totalShotsFired = 0;
        this.roundShotsFired = 0;
        
        // Game objects
        this.scaleFactor = 1; // Initialize scale factor
        this.ship = new Ship(this.canvas.width/2, this.canvas.height/2, this.scaleFactor);
        this.asteroids = [];
        this.bullets = [];
        
        // Shooting limiter
        this.lastShotTime = 0;
        this.shotCooldown = 200; // milliseconds between shots (5 shots per second)
        
        // Event listeners - Desktop only
        this.setupEventListeners();
    }
    
    spawnAsteroids(count) {
        const scaleFactor = this.scaleFactor || 1;
        for (let i = 0; i < count; i++) {
            this.asteroids.push(new Asteroid(
                Math.random() * this.canvas.width,
                Math.random() * this.canvas.height,
                'large',
                this.coinImage,
                scaleFactor
            ));
        }
    }
    
    setupEventListeners() {
        // Desktop keyboard controls
        document.addEventListener('keydown', (e) => this.handleKeyDown(e));
        document.addEventListener('keyup', (e) => this.handleKeyUp(e));
        
        // Debug: Check if button elements exist
        const startBtn = document.getElementById('startGameBtn');
        const playAgainBtn = document.getElementById('playAgain');
        const endGameBtn = document.getElementById('endGameBtn');
        
        console.log('Button elements found:', {
            startGameBtn: !!startBtn,
            playAgain: !!playAgainBtn,
            endGameBtn: !!endGameBtn
        });
        
        if (startBtn) {
            startBtn.addEventListener('click', () => {
                console.log('BEGIN MISSION button clicked!');
                this.startGame();
            });
        } else {
            console.error('startGameBtn not found!');
        }
        
        if (playAgainBtn) {
            playAgainBtn.addEventListener('click', () => this.resetGame());
        }
        
        if (endGameBtn) {
            endGameBtn.addEventListener('click', () => window.location.href = 'logout.php');
        }
    }
    
    startGame() {
        console.log('startGame() called!');
        
        // Hide the Begin Mission button
        const playNowButton = document.getElementById('playNowButton');
        console.log('Play now button:', playNowButton);
        if (playNowButton) {
            playNowButton.style.display = 'none';
        }
        
        // Initialize game
        this.gameActive = true;
        this.gameStarted = true;
        
        // Spawn initial asteroids
        this.spawnAsteroids(4);
        
        // Start game loop
        this.gameLoop();
        
        // Start new game in database
        this.startNewGame();
    }
    
    drawWaitingScreen() {
        console.log('Drawing waiting screen...');
        console.log('Canvas dimensions:', this.canvas.width, 'x', this.canvas.height);
        console.log('Canvas context:', this.ctx);
        
        // Test basic drawing
        if (this.ctx) {
            // Fill entire canvas with black background
            this.ctx.fillStyle = 'black';
            this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
            
            console.log('Black canvas drawn');
        } else {
            console.error('Canvas context is null!');
        }
    }
    
    fireBullet() {
        if (!this.gameActive || this.gamePaused || this.isRespawning) return;
        
        // Check shooting cooldown
        const currentTime = Date.now();
        if (currentTime - this.lastShotTime < this.shotCooldown) {
            return; // Can't shoot yet
        }
        
        const scaleFactor = this.scaleFactor || 1;
        this.bullets.push(new Bullet(
            this.ship.x,
            this.ship.y,
            this.ship.angle,
            scaleFactor
        ));
        
        // Update last shot time
        this.lastShotTime = currentTime;
        
        // Track shots fired
        this.totalShotsFired++;
        this.roundShotsFired++;
    }
    
    gameLoop() {
        if (!this.gameActive) {
            return;
        }
        
        if (this.gamePaused) {
            requestAnimationFrame(() => this.gameLoop());
            return;
        }
        
        this.update();
        this.draw();
        
        requestAnimationFrame(() => this.gameLoop());
    }
    
    update() {
        // Handle respawn timer
        if (this.isRespawning) {
            this.respawnTimer--;
            if (this.respawnTimer <= 0) {
                // Resawn ship
                this.ship.reset(this.canvas.width, this.canvas.height);
                this.ship.visible = true;
                this.isRespawning = false;
            }
        }
        
        // Update ship only if not respawning
        if (!this.isRespawning) {
            this.ship.update(this.canvas.width, this.canvas.height);
        }
        
        // Update bullets and remove off-screen or expired ones
        this.bullets = this.bullets.filter(bullet => {
            bullet.update();
            // Keep bullet if it's on screen and not expired
            return bullet.x > -50 && bullet.x < this.canvas.width + 50 && 
                   bullet.y > -50 && bullet.y < this.canvas.height + 50 &&
                   !bullet.isExpired();
        });
        
        // Update asteroids
        this.asteroids.forEach(asteroid => asteroid.update(this.canvas.width, this.canvas.height));
        
        // Update explosions
        for (let i = this.explosions.length - 1; i >= 0; i--) {
            this.explosions[i].update();
            if (this.explosions[i].finished) {
                this.explosions.splice(i, 1);
            }
        }
        
        // Remove expired small asteroids
        const currentTime = Date.now();
        for (let i = this.asteroids.length - 1; i >= 0; i--) {
            if (this.asteroids[i].size === 'small' && 
                currentTime - this.asteroids[i].creationTime > 15000) {
                this.asteroids.splice(i, 1);
            }
        }
        
        // Check collisions
        this.checkCollisions();
        
        // Check round completion
        if (this.asteroids.length === 0) {
            this.nextRound();
        }
    }
    
    checkCollisions() {
        // Bullet-asteroid collisions
        for (let i = this.bullets.length - 1; i >= 0; i--) {
            for (let j = this.asteroids.length - 1; j >= 0; j--) {
                if (this.checkCollision(this.bullets[i], this.asteroids[j])) {
                    const asteroid = this.asteroids[j];
                    
                    // Add score based on asteroid size
                    switch(asteroid.size) {
                        case 'large': this.addScore(20); break;
                        case 'medium': this.addScore(30); break;
                        case 'small': this.addScore(50); break;
                    }
                    
                    // Track asteroids destroyed
                    this.totalAsteroidsDestroyed++;
                    this.roundAsteroidsDestroyed++;
                    
                    // Create explosion effect
                    this.explosions.push(new Explosion(asteroid.x, asteroid.y));
                    
                    // Split asteroid if not small
                    if (asteroid.size !== 'small') {
                        const newSize = asteroid.size === 'large' ? 'medium' : 'small';
                        const scaleFactor = this.scaleFactor || 1;
                        this.asteroids.push(new Asteroid(asteroid.x, asteroid.y, newSize, this.coinImage, scaleFactor));
                        this.asteroids.push(new Asteroid(asteroid.x, asteroid.y, newSize, this.coinImage, scaleFactor));
                    }
                    
                    // Remove bullet and asteroid
                    this.bullets.splice(i, 1);
                    this.asteroids.splice(j, 1);
                    break;
                }
            }
        }
        
        // Ship-asteroid collisions
        this.asteroids.forEach((asteroid, index) => {
            if (this.checkCollision(this.ship, asteroid)) {
                // Create ship explosion first
                this.createShipExplosion();
                
                // Then lose life and handle game logic
                this.loseLife();
                this.asteroids.splice(index, 1);
            }
        });
    }
    
    checkCollision(obj1, obj2) {
        const dx = obj1.x - obj2.x;
        const dy = obj1.y - obj2.y;
        const distance = Math.sqrt(dx * dx + dy * dy);
        return distance < obj1.radius + obj2.radius;
    }
    
    addScore(points) {
        this.score += points;
        this.roundScore += points;
        this.updateUI();
    }
    
    createShipExplosion() {
        // Create a larger, more dramatic explosion for the ship
        const shipExplosion = {
            x: this.ship.x,
            y: this.ship.y,
            particles: [],
            shockwaves: [],
            debris: [],
            finished: false,
            age: 0,
            maxAge: 90,
            
            update() {
                this.age++;
                
                // Update particles
                for (let i = this.particles.length - 1; i >= 0; i--) {
                    const particle = this.particles[i];
                    
                    particle.x += particle.vx;
                    particle.y += particle.vy;
                    particle.vx *= 0.98;
                    particle.vy *= 0.98;
                    particle.life -= particle.decay;
                    particle.size *= 0.97;
                    
                    if (particle.life <= 0 || particle.size < 0.1) {
                        this.particles.splice(i, 1);
                    }
                }
                
                // Update debris
                for (let i = this.debris.length - 1; i >= 0; i--) {
                    const piece = this.debris[i];
                    piece.x += piece.vx;
                    piece.y += piece.vy;
                    piece.vx *= 0.99;
                    piece.vy *= 0.99;
                    piece.angle += piece.rotationSpeed;
                    piece.life -= 0.01;
                    
                    if (piece.life <= 0) {
                        this.debris.splice(i, 1);
                    }
                }
                
                // Update shockwaves
                for (let i = this.shockwaves.length - 1; i >= 0; i--) {
                    const wave = this.shockwaves[i];
                    wave.radius += wave.speed;
                    wave.opacity -= 0.015;
                    
                    if (wave.opacity <= 0) {
                        this.shockwaves.splice(i, 1);
                    }
                }
                
                if (this.age > this.maxAge && this.particles.length === 0 && this.debris.length === 0) {
                    this.finished = true;
                }
            },
            
            draw(ctx) {
                ctx.save();
                
                // Draw shockwaves
                this.shockwaves.forEach(wave => {
                    ctx.globalAlpha = wave.opacity;
                    ctx.strokeStyle = wave.color;
                    ctx.lineWidth = 3;
                    ctx.shadowColor = wave.color;
                    ctx.shadowBlur = 15;
                    
                    ctx.beginPath();
                    ctx.arc(this.x, this.y, wave.radius, 0, Math.PI * 2);
                    ctx.stroke();
                });
                
                // Draw debris
                this.debris.forEach(piece => {
                    ctx.save();
                    ctx.globalAlpha = piece.life;
                    ctx.translate(piece.x, piece.y);
                    ctx.rotate(piece.angle);
                    ctx.fillStyle = piece.color;
                    ctx.strokeStyle = '#ffffff';
                    ctx.lineWidth = 1;
                    
                    // Draw ship piece
                    ctx.beginPath();
                    ctx.moveTo(piece.points[0].x, piece.points[0].y);
                    piece.points.forEach(point => {
                        ctx.lineTo(point.x, point.y);
                    });
                    ctx.closePath();
                    ctx.fill();
                    ctx.stroke();
                    
                    ctx.restore();
                });
                
                // Draw particles
                this.particles.forEach(particle => {
                    ctx.globalAlpha = particle.life;
                    ctx.fillStyle = particle.color;
                    ctx.shadowColor = particle.color;
                    ctx.shadowBlur = 15;
                    
                    ctx.beginPath();
                    ctx.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2);
                    ctx.fill();
                });
                
                ctx.restore();
            }
        };
        
        // Create explosion particles (more than regular explosion)
        const particleCount = 40 + Math.random() * 20;
        for (let i = 0; i < particleCount; i++) {
            const angle = (Math.PI * 2 * i) / particleCount + Math.random() * 0.5;
            const speed = 3 + Math.random() * 6;
            const size = 2 + Math.random() * 4;
            
            const particleType = Math.random();
            let color;
            
            if (particleType < 0.2) {
                color = '#ffffff'; // White hot
            } else if (particleType < 0.4) {
                color = '#00ffff'; // Cyan (ship color)
            } else if (particleType < 0.7) {
                color = `hsl(${20 + Math.random() * 40}, 100%, ${50 + Math.random() * 30}%)`; // Orange/yellow
            } else {
                color = `hsl(${0 + Math.random() * 20}, 100%, ${40 + Math.random() * 20}%)`; // Red
            }
            
            shipExplosion.particles.push({
                x: this.ship.x,
                y: this.ship.y,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                size: size,
                color: color,
                life: 1,
                decay: 0.01 + Math.random() * 0.02
            });
        }
        
        // Create ship debris
        const debrisCount = 8 + Math.random() * 4;
        for (let i = 0; i < debrisCount; i++) {
            const angle = Math.random() * Math.PI * 2;
            const speed = 1 + Math.random() * 3;
            
            // Create random ship piece shapes
            const points = [];
            const pointCount = 3 + Math.floor(Math.random() * 3);
            for (let j = 0; j < pointCount; j++) {
                points.push({
                    x: (Math.random() - 0.5) * 15,
                    y: (Math.random() - 0.5) * 15
                });
            }
            
            shipExplosion.debris.push({
                x: this.ship.x,
                y: this.ship.y,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                angle: Math.random() * Math.PI * 2,
                rotationSpeed: (Math.random() - 0.5) * 0.2,
                points: points,
                color: `hsl(${200 + Math.random() * 60}, 70%, ${40 + Math.random() * 30}%)`,
                life: 1
            });
        }
        
        // Create shockwaves
        for (let i = 0; i < 3; i++) {
            shipExplosion.shockwaves.push({
                radius: 10 + i * 15,
                speed: 3 + i * 0.5,
                opacity: 1 - i * 0.2,
                color: i === 0 ? '#ffffff' : i === 1 ? '#00ffff' : '#ffaa00'
            });
        }
        
        this.explosions.push(shipExplosion);
    }
    
    loseLife() {
        this.lives--;
        this.updateUI();
        
        if (this.lives <= 0) {
            this.gameOver();
        } else {
            // Start respawn delay instead of immediate reset
            this.isRespawning = true;
            this.respawnTimer = this.respawnDelay;
            
            // Hide the ship during respawn
            this.ship.visible = false;
        }
    }
    
    nextRound() {
        // Save completed round statistics
        this.saveRoundScore();
        
        this.currentRound++;
        this.round++;
        this.roundScore = 0;
        this.roundAsteroidsDestroyed = 0;
        this.roundShotsFired = 0;
        this.spawnAsteroids(4 + this.round); // Increase difficulty
        this.updateUI();
    }
    
    gameOver() {
        this.gameActive = false;
        
        // Only save once per game
        if (!this.gameOverSaved) {
            this.gameOverSaved = true;
            this.saveFinalScore();
        }
        
        document.getElementById('finalScore').textContent = this.score;
        document.getElementById('gameOver').classList.remove('hidden');
    }
    
    resetGame() {
        this.lives = 5;
        this.round = 1;
        this.currentRound = 1;
        this.score = 0;
        this.roundScore = 0;
        this.gameOverSaved = false; // Reset the saved flag
        this.currentGameId = null; // Reset game ID for new game
        
        // Reset statistics
        this.totalAsteroidsDestroyed = 0;
        this.roundAsteroidsDestroyed = 0;
        this.totalShotsFired = 0;
        this.roundShotsFired = 0;
        
        this.asteroids = [];
        this.bullets = [];
        this.explosions = [];
        this.gameActive = true;
        this.ship.reset(this.canvas.width, this.canvas.height);
        
        document.getElementById('gameOver').classList.add('hidden');
        this.spawnAsteroids(4);
        this.updateUI();
        this.startNewGame();
        this.gameLoop();
    }
    
    updateUI() {
        document.getElementById('lives').textContent = this.lives;
        document.getElementById('round').textContent = this.round;
        document.getElementById('score').textContent = this.score;
        
        // Update pause indicator
        const pauseIndicator = document.getElementById('pauseIndicator');
        if (pauseIndicator) {
            pauseIndicator.textContent = this.gamePaused ? 'PAUSED' : '';
            pauseIndicator.style.display = this.gamePaused ? 'block' : 'none';
        }
    }
    
    togglePause() {
        this.gamePaused = !this.gamePaused;
        this.updateUI();
    }
    
    async startNewGame() {
        // Prevent multiple game creations
        if (this.currentGameId) {
            // Game already has ID, skipping creation
            return;
        }
        
        try {
            const response = await fetch('save_game.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'start_game'})
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            this.currentGameId = data.game_id;
        } catch (error) {
            // Error starting new game
        }
    }
    
    async saveFinalScore() {
        if (!this.currentGameId) {
            return;
        }
        
        // Calculate accuracy
        const accuracy = this.totalShotsFired > 0 ? 
            Math.round((this.totalAsteroidsDestroyed / this.totalShotsFired) * 100) : 0;
        
        const payload = {
            action: 'save_final_score',
            game_id: this.currentGameId,
            round_number: this.currentRound, // Save current round (not -1)
            score: this.score, // Save total score at death
            asteroids_destroyed: this.totalAsteroidsDestroyed,
            shots_fired: this.totalShotsFired,
            accuracy: accuracy
        };
        
        try {
            const response = await fetch('save_game.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
        } catch (error) {
            // Error saving final score
        }
    }
    
    async saveRoundScore() {
        if (!this.currentGameId) return;
        
        // Don't save if round score is 0 (unless it's the very first round)
        if (this.roundScore === 0 && this.currentRound > 1) return;
        
        // Calculate accuracy for this round
        const roundAccuracy = this.roundShotsFired > 0 ? 
            Math.round((this.roundAsteroidsDestroyed / this.roundShotsFired) * 100) : 0;
        
        try {
            const response = await fetch('save_game.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'save_round',
                    game_id: this.currentGameId,
                    round_number: this.currentRound - 1, // Save completed round (current - 1)
                    score: this.roundScore,
                    asteroids_destroyed: this.roundAsteroidsDestroyed,
                    shots_fired: this.roundShotsFired,
                    accuracy: roundAccuracy
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Round statistics saved successfully
        } catch (error) {
            // Error saving round score
        }
    }
}

class Ship {
    constructor(x, y, scaleFactor = 1) {
        this.x = x;
        this.y = y;
        this.angle = 0;
        this.vx = 0;
        this.vy = 0;
        this.rotateLeft = false;
        this.rotateRight = false;
        this.thrusting = false;
        this.radius = 24 * scaleFactor;
        this.thrustIntensity = 0;
        this.visible = true; // Ship visibility for respawn system
        
        // New visual properties
        this.shieldActive = false;
        this.shieldEnergy = 100;
        this.shieldRechargeRate = 0.5;
        this.shieldDrainRate = 2;
        this.shieldRadius = this.radius * 1.5;
        
        // Color scheme
        this.primaryColor = '#ffffff';
        this.secondaryColor = '#6666ff';
        this.accentColor = '#00ffff';
        this.cockpitColor = '#66ccff';
        
        // Engine properties for visual effects
        this.engineHeat = 0;
        this.engineGlow = 0;
    }
    
    update(canvasWidth, canvasHeight) {
        // Rotation
        if (this.rotateLeft) this.angle -= 0.05;
        if (this.rotateRight) this.angle += 0.05;
        
        // Thrust with intensity buildup and engine heat
        if (this.thrusting) {
            this.vx += Math.cos(this.angle) * 0.1;
            this.vy += Math.sin(this.angle) * 0.1;
            this.thrustIntensity = Math.min(this.thrustIntensity + 0.1, 1);
            this.engineHeat = Math.min(this.engineHeat + 2, 100);
            this.engineGlow = Math.min(this.engineGlow + 5, 255);
        } else {
            this.thrustIntensity = Math.max(this.thrustIntensity - 0.05, 0);
            this.engineHeat = Math.max(this.engineHeat - 1, 0);
            this.engineGlow = Math.max(this.engineGlow - 2, 0);
        }
        
        // Shield management
        if (this.shieldActive && this.shieldEnergy > 0) {
            this.shieldEnergy -= this.shieldDrainRate;
            if (this.shieldEnergy <= 0) {
                this.shieldActive = false;
                this.shieldEnergy = 0;
            }
        } else if (!this.shieldActive && this.shieldEnergy < 100) {
            this.shieldEnergy += this.shieldRechargeRate;
        }
        
        // Apply velocity
        this.x += this.vx;
        this.y += this.vy;
        
        // Screen wrapping
        if (this.x < 0) this.x = canvasWidth;
        if (this.x > canvasWidth) this.x = 0;
        if (this.y < 0) this.y = canvasHeight;
        if (this.y > canvasHeight) this.y = 0;
        
        // Friction
        this.vx *= 0.99;
        this.vy *= 0.99;
    }
    
    reset(canvasWidth, canvasHeight) {
        this.x = canvasWidth / 2;
        this.y = canvasHeight / 2;
        this.vx = 0;
        this.vy = 0;
        this.angle = 0;
        this.thrustIntensity = 0;
        this.engineHeat = 0;
        this.engineGlow = 0;
        this.shieldEnergy = 100;
        this.shieldActive = false;
    }
    
    draw(ctx) {
        // Don't draw if ship is not visible (during respawn)
        if (this.visible === false) return;
        
        // First draw the thrust flames (behind the ship)
        if (this.thrusting || this.thrustIntensity > 0) {
            this.drawThrustFlames(ctx);
        }
        
        // Draw ship body
        ctx.save();
        ctx.translate(this.x, this.y);
        ctx.rotate(this.angle);
        
        // Ship glow effect
        if (this.shieldActive && this.shieldEnergy > 0) {
            ctx.shadowColor = this.accentColor;
            ctx.shadowBlur = 20;
        } else {
            // Engine glow effect when thrusting
            const glowIntensity = Math.max(this.engineGlow / 255, 0.3);
            ctx.shadowColor = `rgba(255, 100, 0, ${glowIntensity})`;
            ctx.shadowBlur = 10 + this.engineGlow / 25;
        }
        
        // Create gradient for ship body
        const gradientX1 = -20 * Math.cos(this.angle);
        const gradientY1 = -20 * Math.sin(this.angle);
        const gradient = ctx.createLinearGradient(gradientX1, gradientY1, 30, 0);
        gradient.addColorStop(0, this.secondaryColor);
        gradient.addColorStop(0.5, this.primaryColor);
        gradient.addColorStop(1, this.secondaryColor);
        
        // Main ship body
        ctx.fillStyle = gradient;
        ctx.strokeStyle = 'white';
        ctx.lineWidth = 3;
        ctx.lineJoin = 'round';
        
        // Draw filled ship with outline
        ctx.beginPath();
        ctx.moveTo(30, 0);    // Nose
        ctx.lineTo(-20, -20); // Left rear
        ctx.lineTo(-16, -10); // Left wing notch
        ctx.lineTo(-30, -10); // Left wing
        ctx.lineTo(-24, 0);   // Left engine
        ctx.lineTo(-30, 10);  // Right wing
        ctx.lineTo(-16, 10);  // Right wing notch
        ctx.lineTo(-20, 20);  // Right rear
        ctx.closePath();
        ctx.fill();
        ctx.stroke();
        
        // Engine exhaust port with heat effect
        const heatColor = this.getEngineHeatColor();
        ctx.fillStyle = heatColor;
        ctx.fillRect(-24, -6, 8, 12);  // Engine exhaust port
        
        // Engine details
        ctx.fillStyle = '#222222';
        ctx.fillRect(-22, -4, 4, 8);
        
        // Window/cockpit with glass effect
        this.drawCockpit(ctx);
        
        // Ship details/panels
        this.drawShipDetails(ctx);
        
        ctx.restore();
        
        // Draw shield if active
        if (this.shieldActive && this.shieldEnergy > 0) {
            this.drawShield(ctx);
        }
    }
    
    drawCockpit(ctx) {
        // Cockpit window with glass gradient
        const glassGradient = ctx.createRadialGradient(5, 0, 0, 5, 0, 8);
        glassGradient.addColorStop(0, '#aaddff');
        glassGradient.addColorStop(0.7, this.cockpitColor);
        glassGradient.addColorStop(1, '#3388aa');
        
        ctx.fillStyle = glassGradient;
        ctx.beginPath();
        ctx.arc(10, 0, 6, 0, Math.PI * 2);
        ctx.fill();
        
        // Cockpit frame
        ctx.strokeStyle = '#333333';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.arc(10, 0, 6, 0, Math.PI * 2);
        ctx.stroke();
        
        // Cockpit highlight
        ctx.fillStyle = 'rgba(255, 255, 255, 0.3)';
        ctx.beginPath();
        ctx.arc(8, -2, 2, 0, Math.PI * 2);
        ctx.fill();
    }
    
    drawShipDetails(ctx) {
        // Panel lines
        ctx.strokeStyle = 'rgba(100, 100, 255, 0.5)';
        ctx.lineWidth = 1;
        
        // Center line
        ctx.beginPath();
        ctx.moveTo(25, 0);
        ctx.lineTo(-15, 0);
        ctx.stroke();
        
        // Wing panels
        ctx.beginPath();
        ctx.moveTo(-10, -8);
        ctx.lineTo(-25, -8);
        ctx.moveTo(-10, 8);
        ctx.lineTo(-25, 8);
        ctx.stroke();
        
        // Decorative elements
        ctx.fillStyle = this.accentColor;
        // Nose tip
        ctx.beginPath();
        ctx.arc(28, 0, 2, 0, Math.PI * 2);
        ctx.fill();
        
        // Wing tips
        ctx.beginPath();
        ctx.arc(-28, -8, 1.5, 0, Math.PI * 2);
        ctx.fill();
        ctx.beginPath();
        ctx.arc(-28, 8, 1.5, 0, Math.PI * 2);
        ctx.fill();
    }
    
    drawThrustFlames(ctx) {
        ctx.save();
        ctx.translate(this.x, this.y);
        ctx.rotate(this.angle);
        
        // Calculate flame size based on thrust intensity
        const baseLength = 40 * this.thrustIntensity;
        const baseWidth = 16 * this.thrustIntensity;
        
        // Create fiery gradient effect with heat-based colors
        const heat = this.engineHeat / 100;
        const innerColor = this.getFlameColor(heat, 0);
        const middleColor = this.getFlameColor(heat, 0.5);
        const outerColor = this.getFlameColor(heat, 1);
        
        const layers = [
            {color: innerColor, width: baseWidth * 0.8, length: baseLength * 0.7},
            {color: middleColor, width: baseWidth * 0.6, length: baseLength * 0.9},
            {color: outerColor, width: baseWidth * 0.4, length: baseLength * 1.1}
        ];
        
        // Draw multiple flame layers for depth
        layers.forEach(layer => {
            ctx.strokeStyle = layer.color;
            ctx.lineWidth = layer.width;
            ctx.lineCap = 'round';
            ctx.shadowColor = layer.color;
            ctx.shadowBlur = layer.width * 2;
            
            // Main flame
            ctx.beginPath();
            ctx.moveTo(-24, 0);
            ctx.lineTo(-24 - layer.length * (0.7 + Math.random() * 0.3), 
                       (Math.random() - 0.5) * 4);
            ctx.stroke();
            
            // Side flames
            ctx.beginPath();
            ctx.moveTo(-24, -4);
            ctx.lineTo(-24 - layer.length * (0.5 + Math.random() * 0.2), 
                       -8 + (Math.random() - 0.5) * 2);
            ctx.stroke();
            
            ctx.beginPath();
            ctx.moveTo(-24, 4);
            ctx.lineTo(-24 - layer.length * (0.5 + Math.random() * 0.2), 
                       8 + (Math.random() - 0.5) * 2);
            ctx.stroke();
        });
        
        // Add glowing particle effect
        if (this.thrustIntensity > 0.3) {
            const particleCount = Math.floor(this.thrustIntensity * 8);
            for (let i = 0; i < particleCount; i++) {
                const offsetX = -24 - Math.random() * baseLength * 1.2;
                const offsetY = (Math.random() - 0.5) * 12;
                const size = Math.random() * 4 * this.thrustIntensity;
                const alpha = 0.3 + Math.random() * 0.7;
                
                // Vary particle colors
                const particleColors = ['#FFFF00', '#FF6600', '#FF3300', '#FF9900'];
                ctx.fillStyle = particleColors[Math.floor(Math.random() * particleColors.length)];
                ctx.globalAlpha = alpha;
                
                ctx.beginPath();
                ctx.arc(offsetX, offsetY, size, 0, Math.PI * 2);
                ctx.fill();
                
                // Reset alpha
                ctx.globalAlpha = 1;
            }
        }
        
        ctx.restore();
    }
    
    drawShield(ctx) {
        ctx.save();
        ctx.translate(this.x, this.y);
        
        // Pulsing shield effect
        const pulse = Math.sin(Date.now() * 0.01) * 0.1 + 0.9;
        const alpha = (this.shieldEnergy / 100) * 0.7;
        
        // Shield gradient
        const shieldGradient = ctx.createRadialGradient(0, 0, this.radius, 0, 0, this.shieldRadius);
        shieldGradient.addColorStop(0, `rgba(0, 200, 255, ${alpha * 0.3})`);
        shieldGradient.addColorStop(1, `rgba(0, 100, 255, ${alpha * 0.1})`);
        
        ctx.strokeStyle = shieldGradient;
        ctx.fillStyle = shieldGradient;
        ctx.lineWidth = 2;
        ctx.setLineDash([8, 4]);
        
        // Outer shield ring
        ctx.beginPath();
        ctx.arc(0, 0, this.shieldRadius * pulse, 0, Math.PI * 2);
        ctx.stroke();
        
        // Inner shield glow
        ctx.beginPath();
        ctx.arc(0, 0, this.shieldRadius * pulse * 0.9, 0, Math.PI * 2);
        ctx.stroke();
        
        ctx.setLineDash([]);
        ctx.restore();
    }
    
    getEngineHeatColor() {
        const heat = this.engineHeat / 100;
        if (heat < 0.3) return '#333333';
        if (heat < 0.6) return '#664422';
        if (heat < 0.8) return '#aa5522';
        return '#ff6622';
    }
    
    getFlameColor(heat, position) {
        // position: 0 = inner (hottest), 1 = outer (coolest)
        const r = Math.floor(255 * (1 - position * 0.5) * (0.8 + heat * 0.2));
        const g = Math.floor(150 * (1 - position) * (0.6 + heat * 0.4));
        const b = Math.floor(50 * (1 - position));
        return `rgb(${r}, ${g}, ${b})`;
    }
    
    // Helper methods for controlling the ship
    toggleShield() {
        if (this.shieldEnergy > 10) {
            this.shieldActive = !this.shieldActive;
        } else {
            this.shieldActive = false;
        }
    }
    
    setColors(primary, secondary, accent, cockpit) {
        this.primaryColor = primary || this.primaryColor;
        this.secondaryColor = secondary || this.secondaryColor;
        this.accentColor = accent || this.accentColor;
        this.cockpitColor = cockpit || this.cockpitColor;
    }
    
    // Predefined color schemes
    setColorScheme(scheme) {
        const schemes = {
            default: {
                primary: '#ffffff',
                secondary: '#6666ff', 
                accent: '#00ffff',
                cockpit: '#66ccff'
            },
            red: {
                primary: '#ff8888',
                secondary: '#ff3333',
                accent: '#ff0000',
                cockpit: '#ff6666'
            },
            green: {
                primary: '#88ff88',
                secondary: '#33cc33',
                accent: '#00ff00',
                cockpit: '#66ff66'
            },
            stealth: {
                primary: '#888888',
                secondary: '#444444',
                accent: '#222222',
                cockpit: '#666666'
            },
            gold: {
                primary: '#ffdd88',
                secondary: '#ffaa00',
                accent: '#ff8800',
                cockpit: '#ffcc66'
            }
        };
        
        if (schemes[scheme]) {
            this.setColors(
                schemes[scheme].primary,
                schemes[scheme].secondary,
                schemes[scheme].accent,
                schemes[scheme].cockpit
            );
        }
    }
}

// Asteroid class
class Asteroid {
    constructor(x, y, size, coinImage, scaleFactor = 1) {
        this.x = x;
        this.y = y;
        this.size = size;
        this.image = coinImage;
        
        // Set properties based on size with scaling
        switch(size) {
            case 'large':
                this.radius = 30 * scaleFactor;
                this.speed = 1 * scaleFactor;
                break;
            case 'medium':
                this.radius = 20 * scaleFactor;
                this.speed = 1.5 * scaleFactor;
                break;
            case 'small':
                this.radius = 10 * scaleFactor;
                this.speed = 2 * scaleFactor;
                break;
        }
        
        // Random direction
        const angle = Math.random() * Math.PI * 2;
        this.vx = Math.cos(angle) * this.speed;
        this.vy = Math.sin(angle) * this.speed;
        
        // Small asteroids disappear after 15 seconds
        if (size === 'small') {
            this.creationTime = Date.now();
        }
    }
    
    update(canvasWidth, canvasHeight) {
        this.x += this.vx;
        this.y += this.vy;
        
        // Screen wrapping
        if (this.x < -this.radius) this.x = canvasWidth + this.radius;
        if (this.x > canvasWidth + this.radius) this.x = -this.radius;
        if (this.y < -this.radius) this.y = canvasHeight + this.radius;
        if (this.y > canvasHeight + this.radius) this.y = -this.radius;
    }
    
    draw(ctx) {
        ctx.save();
        ctx.translate(this.x, this.y);
        
        // Create circular clipping mask
        ctx.beginPath();
        ctx.arc(0, 0, this.radius, 0, Math.PI * 2);
        ctx.closePath();
        ctx.clip();
        
        // Draw the coin image scaled to size within the circular mask
        const imageSize = this.radius * 2;
        ctx.drawImage(this.image, -imageSize/2, -imageSize/2, imageSize, imageSize);
        
        ctx.restore();
    }
}

// Bullet class
class Bullet {
    constructor(x, y, angle, scaleFactor = 1) {
        this.x = x;
        this.y = y;
        this.angle = angle;
        this.speed = 8 * scaleFactor;
        this.vx = Math.cos(angle) * this.speed;
        this.vy = Math.sin(angle) * this.speed;
        this.radius = 4 * scaleFactor;
        this.length = 20 * scaleFactor;
        this.trail = [];
        this.maxTrailLength = 8;
        this.lifetime = 60;
        this.age = 0;
        
        // Visual properties
        this.coreColor = '#ffffff';
        this.glowColor = '#00ffff';
        this.trailColor = '#ffaa00';
        this.energyPulse = 0;
    }
    
    update() {
        // Store previous position for trail
        this.trail.push({x: this.x, y: this.y, age: 0});
        if (this.trail.length > this.maxTrailLength) {
            this.trail.shift();
        }
        
        // Update trail ages
        this.trail.forEach(point => point.age++);
        
        // Move bullet
        this.x += this.vx;
        this.y += this.vy;
        
        // Age the bullet
        this.age++;
        
        // Energy pulse effect
        this.energyPulse = Math.sin(this.age * 0.3) * 0.5 + 0.5;
    }
    
    draw(ctx) {
        // Draw trail first (behind bullet)
        this.drawTrail(ctx);
        
        // Draw main bullet
        ctx.save();
        ctx.translate(this.x, this.y);
        ctx.rotate(this.angle);
        
        // Glow effect
        const glowIntensity = 0.7 + this.energyPulse * 0.3;
        ctx.shadowColor = this.glowColor;
        ctx.shadowBlur = 15 * glowIntensity;
        
        // Energy core with gradient
        const coreGradient = ctx.createRadialGradient(0, 0, 0, 0, 0, this.radius * 2);
        coreGradient.addColorStop(0, this.coreColor);
        coreGradient.addColorStop(0.3, this.glowColor);
        coreGradient.addColorStop(1, 'rgba(0, 255, 255, 0)');
        
        ctx.fillStyle = coreGradient;
        ctx.beginPath();
        ctx.arc(0, 0, this.radius * 2, 0, Math.PI * 2);
        ctx.fill();
        
        // Main bullet body (elongated energy bolt)
        const bulletGradient = ctx.createLinearGradient(-this.length/2, 0, this.length/2, 0);
        bulletGradient.addColorStop(0, 'rgba(255, 255, 0, 0.3)');
        bulletGradient.addColorStop(0.5, 'rgba(255, 255, 255, 1)');
        bulletGradient.addColorStop(1, 'rgba(255, 255, 0, 0.3)');
        
        ctx.fillStyle = bulletGradient;
        ctx.strokeStyle = this.coreColor;
        ctx.lineWidth = 2;
        
        // Draw elongated bullet shape
        ctx.beginPath();
        ctx.moveTo(-this.length/2, -this.radius/2);
        ctx.lineTo(this.length/2, 0);
        ctx.lineTo(-this.length/2, this.radius/2);
        ctx.closePath();
        ctx.fill();
        ctx.stroke();
        
        // Energy rings
        ctx.strokeStyle = this.glowColor;
        ctx.lineWidth = 1;
        ctx.globalAlpha = 0.6 * glowIntensity;
        
        // Pulsing rings
        for (let i = 0; i < 2; i++) {
            const ringRadius = this.radius * (1.5 + i * 0.5) * (1 + this.energyPulse * 0.3);
            ctx.beginPath();
            ctx.arc(0, 0, ringRadius, 0, Math.PI * 2);
            ctx.stroke();
        }
        
        ctx.restore();
    }
    
    drawTrail(ctx) {
        ctx.save();
        
        this.trail.forEach((point, index) => {
            const alpha = (1 - point.age / this.maxTrailLength) * 0.6;
            const size = this.radius * (1 - point.age / this.maxTrailLength) * 1.5;
            
            ctx.globalAlpha = alpha;
            ctx.fillStyle = this.trailColor;
            ctx.shadowColor = this.trailColor;
            ctx.shadowBlur = 5;
            
            ctx.beginPath();
            ctx.arc(point.x, point.y, size, 0, Math.PI * 2);
            ctx.fill();
        });
        
        ctx.restore();
    }
    
    // Check if bullet is too old
    isExpired() {
        return this.age > this.lifetime;
    }
}

// Explosion class
class Explosion {
    constructor(x, y) {
        this.x = x;
        this.y = y;
        this.particles = [];
        this.shockwaves = [];
        this.finished = false;
        this.age = 0;
        this.maxAge = 60;
        
        // Create explosion particles
        this.createParticles();
        
        // Create shockwaves
        this.createShockwaves();
    }
    
    createParticles() {
        const particleCount = 20 + Math.random() * 15;
        
        for (let i = 0; i < particleCount; i++) {
            const angle = (Math.PI * 2 * i) / particleCount + Math.random() * 0.5;
            const speed = 2 + Math.random() * 4;
            const size = 1 + Math.random() * 3;
            
            // Different particle types for variety
            const particleType = Math.random();
            let color;
            
            if (particleType < 0.3) {
                // White hot core
                color = '#ffffff';
            } else if (particleType < 0.6) {
                // Yellow/orange flames
                color = `hsl(${30 + Math.random() * 30}, 100%, ${50 + Math.random() * 30}%)`;
            } else {
                // Red/orange embers
                color = `hsl(${0 + Math.random() * 30}, 100%, ${40 + Math.random() * 30}%)`;
            }
            
            this.particles.push({
                x: this.x,
                y: this.y,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                size: size,
                color: color,
                life: 1,
                decay: 0.015 + Math.random() * 0.02,
                trail: []
            });
        }
    }
    
    createShockwaves() {
        // Create 2-3 shockwaves
        const waveCount = 2 + Math.floor(Math.random() * 2);
        
        for (let i = 0; i < waveCount; i++) {
            this.shockwaves.push({
                radius: 5 + i * 10,
                maxRadius: 30 + i * 20,
                speed: 2 + i * 0.5,
                opacity: 0.8 - i * 0.2,
                color: i === 0 ? '#ffffff' : '#ffaa00'
            });
        }
    }
    
    update() {
        this.age++;
        
        // Update particles
        for (let i = this.particles.length - 1; i >= 0; i--) {
            const particle = this.particles[i];
            
            // Store trail position
            particle.trail.push({x: particle.x, y: particle.y, life: 1});
            if (particle.trail.length > 5) {
                particle.trail.shift();
            }
            
            // Update trail
            particle.trail.forEach(point => {
                point.life -= 0.2;
            });
            
            // Update particle
            particle.x += particle.vx;
            particle.y += particle.vy;
            
            // Apply drag
            particle.vx *= 0.98;
            particle.vy *= 0.98;
            
            // Apply gravity (subtle downward pull)
            particle.vy += 0.05;
            
            // Decay life
            particle.life -= particle.decay;
            
            // Shrink particle
            particle.size *= 0.98;
            
            // Remove dead particles
            if (particle.life <= 0 || particle.size < 0.1) {
                this.particles.splice(i, 1);
            }
        }
        
        // Update shockwaves
        for (let i = this.shockwaves.length - 1; i >= 0; i--) {
            const wave = this.shockwaves[i];
            wave.radius += wave.speed;
            wave.opacity -= 0.02;
            
            if (wave.opacity <= 0 || wave.radius > wave.maxRadius) {
                this.shockwaves.splice(i, 1);
            }
        }
        
        // Check if explosion is finished
        if (this.age > this.maxAge && this.particles.length === 0 && this.shockwaves.length === 0) {
            this.finished = true;
        }
    }
    
    draw(ctx) {
        ctx.save();
        
        // Draw shockwaves first (background)
        this.shockwaves.forEach(wave => {
            ctx.globalAlpha = wave.opacity;
            ctx.strokeStyle = wave.color;
            ctx.lineWidth = 2;
            ctx.shadowColor = wave.color;
            ctx.shadowBlur = 10;
            
            ctx.beginPath();
            ctx.arc(this.x, this.y, wave.radius, 0, Math.PI * 2);
            ctx.stroke();
        });
        
        // Draw particles
        this.particles.forEach(particle => {
            // Draw particle trail
            particle.trail.forEach((point, index) => {
                if (point.life > 0) {
                    ctx.globalAlpha = point.life * particle.life * 0.3;
                    ctx.fillStyle = particle.color;
                    ctx.shadowColor = particle.color;
                    ctx.shadowBlur = 5;
                    
                    const trailSize = particle.size * point.life * 0.5;
                    ctx.beginPath();
                    ctx.arc(point.x, point.y, trailSize, 0, Math.PI * 2);
                    ctx.fill();
                }
            });
            
            // Draw main particle
            ctx.globalAlpha = particle.life;
            ctx.fillStyle = particle.color;
            ctx.shadowColor = particle.color;
            ctx.shadowBlur = 10;
            
            ctx.beginPath();
            ctx.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2);
            ctx.fill();
            
            // Add glow effect for bright particles
            if (particle.color === '#ffffff' || particle.life > 0.7) {
                ctx.globalAlpha = particle.life * 0.5;
                ctx.beginPath();
                ctx.arc(particle.x, particle.y, particle.size * 2, 0, Math.PI * 2);
                ctx.fill();
            }
        });
        
        ctx.restore();
    }
}

// Draw function for the game
AsteroidsGame.prototype.draw = function() {
    // Clear canvas
    this.ctx.fillStyle = 'black';
    this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
    
    // Draw objects
    this.ship.draw(this.ctx);
    this.asteroids.forEach(asteroid => asteroid.draw(this.ctx));
    this.bullets.forEach(bullet => bullet.draw(this.ctx));
    this.explosions.forEach(explosion => explosion.draw(this.ctx));
};

// Initialize game when page loads
let game;
window.addEventListener('load', () => {
    game = new AsteroidsGame();
});

// Touch Controls and Device Detection Methods
AsteroidsGame.prototype.detectTouchDevice = function() {
    return 'ontouchstart' in window || navigator.maxTouchPoints > 0 || navigator.msMaxTouchPoints > 0;
};

AsteroidsGame.prototype.setupResponsiveCanvas = function() {
    const resizeCanvas = () => {
        // Define base dimensions for desktop (reference size)
        const baseWidth = 1920;
        const baseHeight = 1080;
        const baseCanvasSize = 1000; // Target canvas size on base screen
        
        // Get current window dimensions
        const windowWidth = window.innerWidth;
        const windowHeight = window.innerHeight;
        
        // Calculate scale factor based on the smaller dimension
        const scaleFactor = Math.min(windowWidth / baseWidth, windowHeight / baseHeight);
        
        // Apply scale factor to canvas size
        let canvasSize = Math.floor(baseCanvasSize * scaleFactor);
        
        // Ensure reasonable bounds
        const minSize = Math.min(300, windowWidth * 0.6); // Minimum for mobile
        const maxSize = Math.min(windowWidth * 0.9, windowHeight * 0.9);
        
        canvasSize = Math.max(minSize, Math.min(canvasSize, maxSize));
        
        // Calculate final scale factor for game objects
        const objectScaleFactor = canvasSize / baseCanvasSize;
        
        // Apply new dimensions
        this.canvas.width = canvasSize;
        this.canvas.height = canvasSize;
        
        // Store scale factor for game objects
        this.scaleFactor = objectScaleFactor;
        
        // Scale game objects
        this.scaleGameObjects(objectScaleFactor);
        
        // Reset ship position if game hasn't started
        if (!this.gameActive && this.ship) {
            this.ship.reset(canvasSize, canvasSize);
        }
        
        console.log(`Canvas: ${canvasSize}x${canvasSize} (Scale: ${scaleFactor.toFixed(2)}, Object Scale: ${objectScaleFactor.toFixed(2)})`);
    };
    
    // Initial resize
    resizeCanvas();
    
    // Resize on window resize
    window.addEventListener('resize', resizeCanvas);
    
    // Resize on orientation change
    window.addEventListener('orientationchange', () => {
        setTimeout(resizeCanvas, 100); // Delay for orientation change completion
    });
};

// Scale all game objects based on canvas size
AsteroidsGame.prototype.scaleGameObjects = function(scaleFactor) {
    // Scale ship size
    if (this.ship) {
        this.ship.radius = 15 * scaleFactor; // Base ship radius is 15
        this.ship.size = 30 * scaleFactor;  // Base ship size is 30
    }
    
    // Scale asteroids (check if array exists)
    if (this.asteroids && Array.isArray(this.asteroids)) {
        this.asteroids.forEach(asteroid => {
            switch(asteroid.size) {
                case 'large':
                    asteroid.radius = 30 * scaleFactor;
                    break;
                case 'medium':
                    asteroid.radius = 20 * scaleFactor;
                    break;
                case 'small':
                    asteroid.radius = 10 * scaleFactor;
                    break;
            }
            asteroid.speed *= scaleFactor; // Also scale speed
        });
    }
    
    // Scale bullets (check if array exists)
    if (this.bullets && Array.isArray(this.bullets)) {
        this.bullets.forEach(bullet => {
            bullet.radius = 3 * scaleFactor; // Base bullet radius is 3
            bullet.speed *= scaleFactor; // Scale bullet speed
        });
    }
    
    // Scale explosion particles (check if array exists)
    if (this.explosions && Array.isArray(this.explosions)) {
        this.explosions.forEach(explosion => {
            explosion.radius = 50 * scaleFactor; // Base explosion radius
            if (explosion.particles && Array.isArray(explosion.particles)) {
                explosion.particles.forEach(particle => {
                    particle.radius = 2 * scaleFactor; // Base particle radius
                });
            }
        });
    }
};

// Desktop keyboard controls
AsteroidsGame.prototype.handleKeyDown = function(e) {
    if (!this.gameActive) return;
    
    // Toggle pause with 'P' key
    if (e.key === 'p' || e.key === 'P') {
        this.togglePause();
        return;
    }
    
    // Don't allow controls when paused
    if (this.gamePaused) return;
    
    // Prevent default behavior for game keys to stop page scrolling
    switch(e.key) {
        case 'ArrowLeft': 
            this.ship.rotateLeft = true; 
            e.preventDefault();
            e.stopPropagation();
            break;
        case 'ArrowRight': 
            this.ship.rotateRight = true; 
            e.preventDefault();
            e.stopPropagation();
            break;
        case 'ArrowUp': 
            this.ship.thrusting = true; 
            e.preventDefault();
            e.stopPropagation();
            break;
        case ' ': 
            this.fireBullet(); 
            e.preventDefault();
            e.stopPropagation();
            break;
    }
};

AsteroidsGame.prototype.handleKeyUp = function(e) {
    if (!this.gameActive) return;
    
    // Prevent default behavior for game keys to stop page scrolling
    switch(e.key) {
        case 'ArrowLeft': 
            this.ship.rotateLeft = false; 
            e.preventDefault();
            e.stopPropagation();
            break;
        case 'ArrowRight': 
            this.ship.rotateRight = false; 
            e.preventDefault();
            e.stopPropagation();
            break;
        case 'ArrowUp': 
            this.ship.thrusting = false; 
            e.preventDefault();
            e.stopPropagation();
            break;
    }
};