    <footer class="footer">
        <div class="footer-content">
            <div class="footer-links">
                <a href="accueil.php">Accueil</a> |
                <a href="contact.php">Contact</a> |
                <a href="cgv.php">C.G.V.</a> |
                <a href="mentions-legales.php">Mentions légales</a> |
                <a href="confidentialite.php">Confidentialité</a>
            </div>

            <div class="social-locations">
                <div class="location">
                    <div class="social-icons">
                        <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="30" height="30" rx="15" fill="#e74c3c"/>
                            <text x="15" y="20" text-anchor="middle" fill="white" font-family="Arial" font-size="16" font-weight="bold">f</text>
                        </svg>
                        <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="30" height="30" rx="15" fill="#e74c3c"/>
                            <rect x="8" y="8" width="14" height="14" rx="3" fill="white"/>
                            <circle cx="15" cy="15" r="3" fill="#e74c3c"/>
                            <circle cx="20" cy="10" r="1.5" fill="#e74c3c"/>
                        </svg>
                    </div>
                    <div class="location-name">Solliès-Pont</div>
                </div>
                
                <div class="location">
                    <div class="social-icons">
                        <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="30" height="30" rx="15" fill="#e74c3c"/>
                            <text x="15" y="20" text-anchor="middle" fill="white" font-family="Arial" font-size="16" font-weight="bold">f</text>
                        </svg>
                        <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="30" height="30" rx="15" fill="#e74c3c"/>
                            <rect x="8" y="8" width="14" height="14" rx="3" fill="white"/>
                            <circle cx="15" cy="15" r="3" fill="#e74c3c"/>
                            <circle cx="20" cy="10" r="1.5" fill="#e74c3c"/>
                        </svg>
                    </div>
                    <div class="location-name">Pierrefeu</div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Consentement cookies (RGPD) -->
    <div class="cookie-consent" id="cookieConsent">
        <div class="cookie-consent-text">
            Nous utilisons des cookies techniques nécessaires au fonctionnement du site (session, panier, préférence de thème).
            Aucun cookie publicitaire ou de traçage tiers n'est utilisé. Voir notre
            <a href="confidentialite.php">politique de confidentialité</a>.
        </div>
        <div class="cookie-consent-actions">
            <button type="button" class="cookie-btn-secondary" id="cookieRefuse">Refuser</button>
            <button type="button" class="cookie-btn-primary" id="cookieAccept">Accepter</button>
        </div>
    </div>

    <style>
        .cookie-consent {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 2000;
            background: var(--surface-deep, #1a1a1a);
            border-top: 1px solid var(--divider, #333);
            padding: 1rem 1.5rem;
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .cookie-consent.show {
            display: flex;
        }

        .cookie-consent-text {
            color: #d7dde0;
            font-size: .82rem;
            line-height: 1.5;
            flex: 1;
            min-width: 240px;
        }

        .cookie-consent-text a {
            color: var(--accent-2, #3498db);
        }

        .cookie-consent-actions {
            display: flex;
            gap: .7rem;
            flex-shrink: 0;
        }

        .cookie-btn-primary,
        .cookie-btn-secondary {
            padding: .6rem 1.3rem;
            border-radius: 6px;
            font-weight: bold;
            font-size: .82rem;
            cursor: pointer;
            border: none;
        }

        .cookie-btn-primary {
            background: var(--accent-2, #3498db);
            color: #fff;
        }

        .cookie-btn-secondary {
            background: transparent;
            border: 1px solid #555;
            color: #d7dde0;
        }

        @media (max-width: 600px) {
            .cookie-consent {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
        }
    </style>
    <script>
        (function () {
            var banner = document.getElementById('cookieConsent');
            var choice = localStorage.getItem('its_cookie_consent');
            if (!choice) {
                banner.classList.add('show');
            }
            function setChoice(value) {
                localStorage.setItem('its_cookie_consent', value);
                localStorage.setItem('its_cookie_consent_date', new Date().toISOString());
                banner.classList.remove('show');
            }
            document.getElementById('cookieAccept').addEventListener('click', function () { setChoice('accepte'); });
            document.getElementById('cookieRefuse').addEventListener('click', function () { setChoice('refuse'); });
        })();
    </script>
</body>
</html>
