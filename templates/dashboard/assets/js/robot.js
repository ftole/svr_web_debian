document.addEventListener("DOMContentLoaded", () => {
    const robotSvg = document.getElementById('robot-svg');
    if (!robotSvg) return;

    const leftPupil = document.getElementById('left-pupil');
    const rightPupil = document.getElementById('right-pupil');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('login-password');
    
    // Estados para pausar el seguimiento del mouse
    let isTyping = false;
    let isBlind = false;

    // Movimiento ocular que sigue el cursor
    document.addEventListener('mousemove', (e) => {
        if (isBlind || isTyping) return;

        // Calcula el centro aproximado de los ojos del robot
        const rect = robotSvg.getBoundingClientRect();
        const robotCenterX = rect.left + rect.width / 2;
        // Compensar hacia arriba para alinear con los ojos
        const robotCenterY = rect.top + rect.height / 2 - 20;

        const deltaX = e.clientX - robotCenterX;
        const deltaY = e.clientY - robotCenterY;
        
        // Ángulo hacia el mouse
        const angle = Math.atan2(deltaY, deltaX);
        
        // Limitar la distancia máxima que pueden viajar las pupilas (radio de 6px)
        const distance = Math.min(Math.hypot(deltaX, deltaY) / 30, 6);

        const moveX = Math.cos(angle) * distance;
        const moveY = Math.sin(angle) * distance;

        if (leftPupil && rightPupil) {
            leftPupil.style.transform = `translate(${moveX}px, ${moveY}px)`;
            rightPupil.style.transform = `translate(${moveX}px, ${moveY}px)`;
        }
    });

    // Eventos para el campo de Usuario (El robot mira de reojo / disimula)
    if (usernameInput) {
        const handleUserFocus = () => {
            isTyping = true;
            isBlind = false;
            robotSvg.classList.add('robot-typing');
            robotSvg.classList.remove('robot-blind');
            
            // Mira hacia arriba y a un lado disimuladamente
            if (leftPupil && rightPupil) {
                leftPupil.style.transform = 'translate(-5px, -5px)';
                rightPupil.style.transform = 'translate(-5px, -5px)';
            }
        };

        usernameInput.addEventListener('focus', handleUserFocus);
        usernameInput.addEventListener('input', handleUserFocus);
        
        usernameInput.addEventListener('blur', () => {
            isTyping = false;
            robotSvg.classList.remove('robot-typing');
            // Centra la vista antes de recuperar el mouse
            if (leftPupil && rightPupil) {
                leftPupil.style.transform = 'translate(0px, 0px)';
                rightPupil.style.transform = 'translate(0px, 0px)';
            }
        });
    }

    // Eventos para el campo de Contraseña (El robot se tapa los ojos)
    if (passwordInput) {
        const handlePassFocus = () => {
            isBlind = true;
            isTyping = false;
            robotSvg.classList.add('robot-blind');
            robotSvg.classList.remove('robot-typing');
            
            // Vuelve las pupilas al centro bajo las manos
            if (leftPupil && rightPupil) {
                leftPupil.style.transform = 'translate(0px, 0px)';
                rightPupil.style.transform = 'translate(0px, 0px)';
            }
        };

        passwordInput.addEventListener('focus', handlePassFocus);
        passwordInput.addEventListener('input', handlePassFocus);
        
        passwordInput.addEventListener('blur', () => {
            isBlind = false;
            robotSvg.classList.remove('robot-blind');
        });
    }
});
