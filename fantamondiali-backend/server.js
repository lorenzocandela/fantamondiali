require('dotenv').config();
const express = require('express');
const cors = require('cors');
const mysql = require('mysql2/promise');
const axios = require('axios');

const app = express();
app.use(cors());
app.use(express.json());

const pool = mysql.createPool({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME
});

app.get('/api/status', (req, res) => {
    res.json({ message: 'Server FantaMondiali operativo!' });
});

app.post('/api/users/sync', async (req, res) => {
    try {
        const { firebase_uid, email, team_name } = req.body;
        
        const [users] = await pool.execute('SELECT * FROM users WHERE username = ?', [firebase_uid]);
        
        if (users.length === 0) {
            await pool.execute(
                'INSERT INTO users (username, password, team_name) VALUES (?, ?, ?)',
                [firebase_uid, 'firebase_auth', team_name || 'Nuova Squadra']
            );
            return res.status(201).json({ message: 'Utente sincronizzato nel DB locale' });
        }
        
        res.status(200).json({ message: 'Utente già presente' });
    } catch (error) {
        console.error(error);
        res.status(500).json({ error: 'Errore del server' });
    }
});

const PORT = process.env.PORT || 3000;

// sync da api
app.get('/api/admin/sync-players', async (req, res) => {
    try {
        await pool.execute('DELETE FROM players');
        console.log("Tabella 'players' azzerata con successo. Inizio sincronizzazione...");

        let targetSeason = 2026;
        let page = 1;
        let totalPages = 1;
        let playersAdded = 0;

        const roleMap = {
            'Goalkeeper': 'POR',
            'Defender': 'DIF',
            'Midfielder': 'CEN',
            'Attacker': 'ATT'
        };

        const fetchPlayersPage = async (season, pageNum) => {
            return await axios.get('https://v3.football.api-sports.io/players', {
                params: { league: 1, season: season, page: pageNum },
                headers: { 'x-apisports-key': process.env.API_CALL }
            });
        };

        let response = await fetchPlayersPage(targetSeason, 1);

        if (response.data.errors && Object.keys(response.data.errors).length > 0) {
            console.error("Errore API-Football:", response.data.errors);
            return res.status(400).json({ error: response.data.errors });
        }

        if (!response.data.response || response.data.response.length === 0) {
            console.log(`Nessun giocatore trovato per il ${targetSeason}. Attivazione fallback sul 2022...`);
            targetSeason = 2022;
            response = await fetchPlayersPage(targetSeason, 1);
        }

        totalPages = response.data.paging.total;

        while (page <= totalPages) {
            console.log(`Scaricamento pagina ${page} di ${totalPages} (Stagione: ${targetSeason})...`);
            
            if (page > 1) {
                response = await fetchPlayersPage(targetSeason, page);
            }

            const players = response.data.response;
            if (!players || players.length === 0) break;

            for (let p of players) {
                const id = p.player.id;
                const name = p.player.name;
                const photo = p.player.photo;
                
                const stats = p.statistics[0];
                if (!stats) continue; 
                
                const positionEn = stats.games.position;
                const country = stats.team.name;
                const positionIta = roleMap[positionEn] || 'ND';

                await pool.execute(
                    `INSERT INTO players (id, name, position, country, image_path) 
                     VALUES (?, ?, ?, ?, ?) 
                     ON DUPLICATE KEY UPDATE name=VALUES(name), position=VALUES(position), country=VALUES(country), image_path=VALUES(image_path)`,
                    [id, name, positionIta, country, photo]
                );
                playersAdded++;
            }
            page++;
        }

        res.json({ message: `Sincronizzazione completata! Salvati ${playersAdded} giocatori della stagione ${targetSeason}.` });
    } catch (error) {
        console.error("Errore script:", error.response ? error.response.data : error.message);
        res.status(500).json({ error: 'Errore durante la sincronizzazione dei giocatori' });
    }
});

// get giocatori db
app.get('/api/players', async (req, res) => {
    try {
        const [players] = await pool.execute('SELECT * FROM players ORDER BY name ASC');
        res.json(players);
    } catch (error) {
        console.error("Errore recupero giocatori:", error);
        res.status(500).json({ error: 'Impossibile caricare i giocatori' });
    }
});

app.listen(PORT, () => {
    console.log(`Server FantaMondiali in ascolto sulla porta ${PORT}`);
});
