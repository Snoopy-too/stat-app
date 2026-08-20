/**
 * The Flying Dutchmen - StatApp i18n Localization Engine
 * Provides multi-language support (English, Spanish, Japanese)
 * with reactivity, parameter interpolation, cookie/storage sync, and DOM auto-translation.
 */

(function(window) {
  'use strict';

  const LOCALES = {
    en: {
      nav: {
        home: 'Home',
        members: 'Members',
        teams: 'Teams',
        champions: 'Champions',
        games: 'Games',
        results: 'Results',
        findClub: 'Find a Club',
        account: 'Account',
        login: 'Sign In',
        logout: 'Sign Out',
        register: 'Register Club',
        admin: 'Admin',
        viewPublic: 'View Public Page',
        switchClub: 'Switch Club',
        settings: 'Settings'
      },
      landing: {
        heroTitle1: 'Track Every Victory.',
        heroTitle2: 'Celebrate Every Champion.',
        heroSubtitle: 'The all-in-one platform for board game clubs to manage members, track game results, and crown champions.',
        freeToUse: 'Free to Use',
        unlimited: 'Games & Members',
        registerClub: 'Register Your Club',
        findYourClub: 'Find Your Club',
        explorePublicClubs: 'Explore Public Clubs',
        searchPlaceholder: 'Search by club name or city...',
        noClubsFound: 'No clubs found matching your search.',
        viewClub: 'View Club',
        featuresHeading: 'Built for Board Game Enthusiasts',
        featMembersTitle: 'Member Management',
        featMembersDesc: 'Keep rosters, record play counts, and track individual stats across all games.',
        featResultsTitle: 'Match Logging',
        featResultsDesc: 'Log individual and team matches with detailed scores, placements, and dates.',
        featChampionsTitle: 'Leaderboards & Champions',
        featChampionsDesc: 'Crown season champions, award custom trophies, and celebrate top performers.'
      },
      club: {
        members: 'Members',
        teams: 'Teams',
        champions: 'Champions',
        games: 'Games',
        results: 'Results',
        membersCount: '{{count}} Members',
        teamsCount: '{{count}} Teams',
        gamesCount: '{{count}} Games',
        resultsCount: '{{count}} Matches',
        championsCount: '{{count}} Champions',
        allGames: 'All Games',
        allMembers: 'All Members',
        allTeams: 'All Teams',
        recentResults: 'Recent Results',
        leaderboard: 'Leaderboard',
        stats: 'Statistics',
        playedCount: 'Played {{count}} times',
        wins: 'Wins',
        winRate: 'Win Rate',
        totalPlays: 'Total Plays',
        viewDetails: 'View Details',
        matchDetails: 'Match Details',
        cumulativeWins: 'Cumulative Wins Over Time',
        last6Months: 'Last 6 Months',
        oneYear: '1 Year',
        twoYears: '2 Years',
        allTime: 'All Time',
        lastMatchPlayed: 'Last Match Played',
        topChampion: 'Top Champion',
        playerCapacity: 'Player Capacity',
        recommendedPlayers: 'Recommended Players',
        totalRecordedMatches: 'Total Recorded Matches',
        matchHistory: 'Match History'
      },
      admin: {
        dashboard: 'Dashboard',
        manageMembers: 'Manage Members',
        manageTeams: 'Manage Teams',
        manageGames: 'Manage Games',
        manageResults: 'Manage Results',
        manageChampions: 'Manage Champions',
        addResult: 'Add Result',
        editClub: 'Edit Club',
        clubSettings: 'Club Settings',
        addNewMember: 'Add a Member',
        saveMember: 'Save Member',
        memberDetails: 'Member Details',
        updateMember: 'Update Member',
        deleteMember: 'Delete Member',
        fullName: 'Full Name',
        nickname: 'Nickname',
        email: 'Email Address',
        club: 'Club',
        status: 'Status',
        addNewTeam: 'Add a Team',
        saveTeam: 'Save Team',
        teamDetails: 'Team Details',
        updateTeam: 'Update Team',
        deleteTeam: 'Delete Team',
        addNewGame: 'Add a Game',
        saveGame: 'Save Game',
        gameDetails: 'Game Details',
        updateGame: 'Update Game',
        deleteGame: 'Delete Game',
        gameOverview: 'Game Overview & Infographics',
        gameOverviewDesc: 'Key statistics and activity summary for this game.',
        currentImage: 'Current Image',
        removeCurrentImage: 'Remove current image',
        gameImage: 'Game Image File',
        uploadZoneText: 'Click to replace or drag & drop file',
        uploadZoneHint: 'JPG, PNG, GIF (Max 1MB)',
        orImageUrl: 'Or Image Link / URL',
        imageUrlHint: 'Paste a direct web link to an image file',
        matchType: 'Game Type',
        winnerLosers: 'Winner / Losers',
        ranked: 'Ranked (1st, 2nd, etc.)',
        teams: 'Teams',
        cooperative: 'Cooperative',
        addNewResult: 'Add New Result',
        crownChampion: 'Add Champion',
        saveChampion: 'Save Champion',
        championDetails: 'Champion Details',
        updateChampion: 'Update Champion',
        deleteChampion: 'Delete Champion',
        selectMember: 'Select Member',
        clubName: 'Club Name:',
        clubNameLabel: 'Club Name',
        clubLogo: 'Club Logo:',
        removeCurrentLogo: 'Remove current logo',
        uploadLogoText: 'Click to upload or drag & drop club logo',
        uploadLogoHint: 'JPG, PNG, GIF (Max 1MB)',
        clubSlug: 'Club URL Slug (optional):',
        clubSlugDesc: 'If set, club will be accessible at domain.com/slug',
        vanityUrl: 'Current Vanity URL:',
        jsonUrl: 'JSON URL:',
        jsonUrlDesc: 'This link will return a JSON object of your club stats.',
        deleteClub: 'Delete Club',
        addNewClub: 'Add a Club',
        saveResult: 'Save Result',
        activateSelected: 'Activate Selected',
        deactivateSelected: 'Deactivate Selected',
        deleteSelected: 'Delete Selected'
      },
      auth: {
        username: 'Username or Email',
        usernameOnly: 'Username',
        usernameHelp: '2 to 50 characters (letters, numbers, underscores).',
        usernamePlaceholder: 'Enter your username or email',
        password: 'Password',
        passwordHelp: 'Min 8 characters with uppercase, lowercase, number, and symbol.',
        passwordPlaceholder: 'Enter your password',
        confirmPassword: 'Confirm Password',
        rememberMe: 'Keep me logged in',
        forgotPassword: 'Forgot Password?',
        signIn: 'Sign In',
        createAccount: 'Create Account',
        registerAccount: 'Register Account',
        signInWithTFD: 'Sign in with The Flying Dutchmen',
        noAccount: "Don't have an account?",
        haveAccount: 'Already have an account?',
        createOne: 'Create one',
        emailHelp: 'Used for admin login and notifications.'
      },
      common: {
        loading: 'Loading...',
        error: 'Error',
        success: 'Success',
        save: 'Save',
        saving: 'Saving...',
        cancel: 'Cancel',
        delete: 'Delete',
        edit: 'Edit',
        back: 'Back',
        close: 'Close',
        actions: 'Actions',
        details: 'Details',
        date: 'Date',
        score: 'Score',
        winner: 'Winner',
        rank: 'Rank',
        view: 'View',
        viewEdit: 'View/Edit',
        filter: 'Filter',
        search: 'Search',
        confirm: 'Confirm',
        yes: 'Yes',
        no: 'No',
        all: 'All',
        none: 'None',
        apply: 'Apply',
        reset: 'Reset',
        selectAll: 'Select All',
        uncheckAll: 'Uncheck All',
        copy: 'Copy',
        or: 'or',
        bulkActions: 'Bulk Actions',
        allStatus: 'All Status',
        saveChanges: 'Save Changes',
        plays: 'Plays',
        players: 'Players',
        wins: 'wins',
        winsCap: 'Wins',
        results: 'Results',
        active: 'Active',
        inactive: 'Inactive',
        viewEdit: 'View/Edit',
        manage: 'Manage',
        managing: 'Managing'
      },
      account: {
        yourClubs: 'Your Clubs',
        noClubsYet: 'You don\'t have any clubs yet. Click "Add a Club" above to create your first club.',
        createNewClub: 'Create New Club',
        themeFor: 'Theme for',
        activeTheme: 'Active Theme',
        clickToApply: 'Click to apply',
        accountInfo: 'Account Information',
        adminDetails: '👤 Administrator Details',
        accountId: 'Account ID',
        accountType: 'Account Type',
        clubQuota: 'Club Quota',
        memberSince: 'Member Since',
        portfolioStats: '📊 Portfolio Statistics',
        totalMembers: 'Total Members',
        totalGames: 'Total Games',
        totalPlaysLogged: 'Total Plays Logged',
        championsCrowned: 'Champions Crowned',
        teamsRegistered: 'Teams Registered',
        activeContext: '🎯 Active Context',
        activeClub: 'Active Club',
        clubId: 'Club ID',
        clubOwner: 'Club Owner',
        administrators: 'Administrators',
        default: 'Default',
        total: 'Total',
        managing: 'Managing',
        manage: 'Manage'
      },
      gametype: {
        winner_losers: 'Winner / Losers',
        ranked: 'Ranked (1st, 2nd, etc.)',
        teams: 'Teams',
        team: 'Teams',
        cooperative: 'Cooperative',
        individual: 'Individual'
      },
      confirm: {
        deleteTitle: 'Confirm Deletion',
        deleteMessage: 'Are you sure you want to delete this item? This action cannot be undone.',
        deleteButton: 'Delete',
        cancelButton: 'Cancel'
      },
      empty: {
        noResults: 'No matches recorded yet.',
        noMembers: 'No members registered yet.',
        noTeams: 'No teams created yet.',
        noGames: 'No games added to the catalog yet.',
        noGamesDesc: 'Add some games to your club before recording results.',
        noChampions: 'No champions crowned yet.'
      },
      validation: {
        requiredField: 'This field is required.',
        invalidEmail: 'Please enter a valid email address.',
        invalidNumber: 'Please enter a valid number.'
      },
      members: {
        name: 'Name',
        nickname: 'Nickname',
        totalWins: 'Total Wins',
        trophies: 'Trophies',
        status: 'Status',
        allStatus: 'All Status',
        active: 'Active',
        inactive: 'Inactive',
        searchPlaceholder: 'Search members...'
      },
      teams: {
        teamName: 'Team Name',
        created: 'Created',
        selectTeamMembers: 'Select Team Members:',
        searchPlaceholder: 'Search teams...'
      },
      champions: {
        seasonTitle: 'Season / Title',
        awarded: 'Date Awarded',
        currentChampion: '👑 Current Champion',
        formerChampion: 'Former Champion',
        searchPlaceholder: 'Search champions...'
      },
      games: {
        image: 'Image',
        gameName: 'Game Name',
        players: 'Players',
        minPlayers: 'Min Players',
        maxPlayers: 'Max Players',
        playCount: 'Play Count',
        searchPlaceholder: 'Search games...'
      },
      results: {
        game: 'Game',
        winner: 'Winner / Outcome',
        duration: 'Duration',
        selectWinner: 'Select Winner',
        selectSecondPlace: 'Select Second Place',
        selectPlace: 'Select {{place}}',
        secondPlace: 'Second Place',
        addPlace: 'Add Place',
        selectWinningTeam: 'Select Winning Team',
        selectLosers: 'Select Losers:',
        outcome: 'Outcome:',
        victory: 'Victory',
        defeat: 'Defeat',
        selectPlayers: 'Select Players:',
        winningTeam: 'Winning Team:',
        selectLosingTeams: 'Select Losing Team(s):',
        notes: 'Notes:',
        backToResults: 'Back to Results',
        deleteResult: 'Delete Result',
        searchPlaceholder: 'Search results...',
        hours: 'hrs',
        minutes: 'mins',
        datePlayed: 'Date Played'
      },
      analytics: {
        title: 'Analytics & Trends',
        memberWinsTitle: 'Wins for Members and Their Teams',
        individualWins: 'Individual Wins',
        teamWins: 'Team Wins',
        mostPlayedGames: 'Most Played Games (All Games)',
        ignored: 'Ignored:',
        resetAll: 'Reset All',
        clickBarHint: 'Click any bar to ignore a game',
        clickBarTooltip: '(Click bar to ignore game)',
        winRatesByGameType: 'Win Rates by Game Type',
        winnerLosersGames: 'Winner/Losers Games',
        rankedGames: 'Ranked Games (1st Place)',
        teamGames: 'Team Games',
        coopGames: 'Cooperative Games'
      },
      headers: {
        accountSettings: 'Account Settings',
        editClub: 'Edit Club',
        viewClub: 'View Club',
        manageClubLogo: 'Manage Club Logo',
        manageTrophyImage: 'Manage Trophy Image',
        selectClub: 'Select Club',
        optimizeCoverImages: 'Optimize Cover Images',
        gameDays: 'Game Days',
        gameDetails: 'Game Details',
        playDetails: 'Play Details',
        teamPlayDetails: 'Team Play Details',
        cooperativePlayDetails: 'Cooperative Play Details',
        viewEditMember: 'View/Edit Member',
        viewEditChampion: 'View/Edit Champion',
        editTeam: 'Edit Team',
        manageMembers: 'Manage {{club}} Members ({{count}})',
        manageTeams: 'Manage {{club}} Teams ({{count}})',
        manageGames: 'Manage {{club}} Games ({{count}})',
        manageChampions: 'Manage {{club}} Champions ({{count}})',
        manageResults: 'Manage {{club}} Results ({{count}})',
        addGameResult: 'Add Game Result ({{club}})',
        editGameResult: 'Edit Game Result ({{club}})',
        gameResultsDate: 'Game Results - {{date}}',
        gamePlayDetails: '{{game}} - Play Details',
        gameTeamPlayDetails: '{{game}} - Team Play Details',
        gameCoopPlayDetails: '{{game}} - Cooperative Play',
        viewAndEditGame: 'View and Edit {{game}}'
      }
    },
    es: {
      nav: {
        home: 'Inicio',
        members: 'Miembros',
        teams: 'Equipos',
        champions: 'Campeones',
        games: 'Juegos',
        results: 'Resultados',
        findClub: 'Buscar Club',
        account: 'Cuenta',
        login: 'Iniciar Sesión',
        logout: 'Cerrar Sesión',
        register: 'Registrar Club',
        admin: 'Administración',
        viewPublic: 'Ver Página Pública',
        switchClub: 'Cambiar de Club',
        settings: 'Configuración'
      },
      landing: {
        heroTitle1: 'Registra Cada Victoria.',
        heroTitle2: 'Celebra a Cada Campeón.',
        heroSubtitle: 'La plataforma integral para que los clubes de juegos de mesa gestionen miembros, registren resultados y coronen campeones.',
        freeToUse: 'Gratis',
        unlimited: 'Juegos y Miembros',
        registerClub: 'Registra tu Club',
        findYourClub: 'Encuentra tu Club',
        explorePublicClubs: 'Explorar Clubes Públicos',
        searchPlaceholder: 'Buscar por nombre de club o ciudad...',
        noClubsFound: 'No se encontraron clubes que coincidan con la búsqueda.',
        viewClub: 'Ver Club',
        featuresHeading: 'Creado para Entusiastas de Juegos de Mesa',
        featMembersTitle: 'Gestión de Miembros',
        featMembersDesc: 'Mantén la lista de integrantes, historial y estadísticas individuales.',
        featResultsTitle: 'Registro de Partidas',
        featResultsDesc: 'Registra partidas individuales y por equipos con puntuaciones y fechas.',
        featChampionsTitle: 'Tablas y Campeones',
        featChampionsDesc: 'Corona a los campeones de temporada y otorga trofeos personalizados.'
      },
      club: {
        members: 'Miembros',
        teams: 'Equipos',
        champions: 'Campeones',
        games: 'Juegos',
        results: 'Resultados',
        membersCount: '{{count}} Miembros',
        teamsCount: '{{count}} Equipos',
        gamesCount: '{{count}} Juegos',
        resultsCount: '{{count}} Partidas',
        championsCount: '{{count}} Campeones',
        allGames: 'Todos los Juegos',
        allMembers: 'Todos los Miembros',
        allTeams: 'Todos los Equipos',
        recentResults: 'Resultados Recientes',
        leaderboard: 'Clasificación',
        stats: 'Estadísticas',
        playedCount: 'Jugado {{count}} veces',
        wins: 'Victorias',
        winRate: '% de Victorias',
        totalPlays: 'Total de Partidas',
        viewDetails: 'Ver Detalles',
        matchDetails: 'Detalles de la Partida',
        cumulativeWins: 'Victorias Acumuladas en el Tiempo',
        last6Months: 'Últimos 6 Meses',
        oneYear: '1 Año',
        twoYears: '2 Años',
        allTime: 'Todo el Tiempo',
        lastMatchPlayed: 'Última Partida Jugada',
        topChampion: 'Máximo Campeón',
        playerCapacity: 'Capacidad de Jugadores',
        recommendedPlayers: 'Jugadores Recomendados',
        totalRecordedMatches: 'Total de Partidas Registradas',
        matchHistory: 'Historial de Partidas'
      },
      admin: {
        dashboard: 'Panel',
        manageMembers: 'Gestionar Miembros',
        manageTeams: 'Gestionar Equipos',
        manageGames: 'Gestionar Juegos',
        manageResults: 'Gestionar Resultados',
        manageChampions: 'Gestionar Campeones',
        addResult: 'Añadir Resultado',
        editClub: 'Editar Club',
        clubSettings: 'Configuración del Club',
        addNewMember: 'Añadir un Miembro',
        saveMember: 'Guardar Miembro',
        memberDetails: 'Detalles del Miembro',
        updateMember: 'Actualizar Miembro',
        deleteMember: 'Eliminar Miembro',
        fullName: 'Nombre Completo',
        nickname: 'Apodo',
        email: 'Correo Electrónico',
        club: 'Club',
        status: 'Estado',
        addNewTeam: 'Añadir un Equipo',
        saveTeam: 'Guardar Equipo',
        teamDetails: 'Detalles del Equipo',
        updateTeam: 'Actualizar Equipo',
        deleteTeam: 'Eliminar Equipo',
        addNewGame: 'Añadir un Juego',
        saveGame: 'Guardar Juego',
        gameDetails: 'Detalles del Juego',
        updateGame: 'Actualizar Juego',
        deleteGame: 'Eliminar Juego',
        gameOverview: 'Resumen e Infografía del Juego',
        gameOverviewDesc: 'Estadísticas clave y resumen de actividad para este juego.',
        currentImage: 'Imagen Actual',
        removeCurrentImage: 'Eliminar imagen actual',
        gameImage: 'Archivo de Imagen del Juego',
        uploadZoneText: 'Haz clic para reemplazar o arrastra y suelta el archivo',
        uploadZoneHint: 'JPG, PNG, GIF (Máx 1MB)',
        orImageUrl: 'O Enlace / URL de la Imagen',
        imageUrlHint: 'Pega un enlace web directo a un archivo de imagen',
        matchType: 'Tipo de Juego',
        winnerLosers: 'Ganador / Perdedores',
        ranked: 'Por Posición (1º, 2º, etc.)',
        teams: 'Equipos',
        cooperative: 'Cooperativo',
        addNewResult: 'Añadir Nuevo Resultado',
        crownChampion: 'Añadir Campeón',
        saveChampion: 'Guardar Campeón',
        championDetails: 'Detalles del Campeón',
        updateChampion: 'Actualizar Campeón',
        deleteChampion: 'Eliminar Campeón',
        selectMember: 'Seleccionar Miembro',
        clubName: 'Nombre del Club:',
        clubNameLabel: 'Nombre del Club',
        clubLogo: 'Logotipo del Club:',
        removeCurrentLogo: 'Eliminar logotipo actual',
        uploadLogoText: 'Haz clic para subir o arrastra y suelta el logotipo',
        uploadLogoHint: 'JPG, PNG, GIF (Máx 1MB)',
        clubSlug: 'Slug URL del Club (opcional):',
        clubSlugDesc: 'Si se establece, se podrá acceder al club en dominio.com/slug',
        vanityUrl: 'URL Personalizada Actual:',
        jsonUrl: 'URL de API JSON:',
        jsonUrlDesc: 'Este enlace devolverá un objeto JSON de las estadísticas de tu club.',
        deleteClub: 'Eliminar Club',
        addNewClub: 'Añadir un Club',
        saveResult: 'Guardar Resultado',
        activateSelected: 'Activar Seleccionados',
        deactivateSelected: 'Desactivar Seleccionados',
        deleteSelected: 'Eliminar Seleccionados'
      },
      auth: {
        username: 'Usuario o Correo Electrónico',
        usernameOnly: 'Usuario',
        usernameHelp: 'De 2 a 50 caracteres (letras, números y guiones bajos).',
        usernamePlaceholder: 'Ingresa tu usuario o correo',
        password: 'Contraseña',
        passwordHelp: 'Mínimo 8 caracteres con mayúscula, minúscula, número y símbolo.',
        passwordPlaceholder: 'Ingresa tu contraseña',
        confirmPassword: 'Confirmar Contraseña',
        rememberMe: 'Recordarme en este equipo',
        forgotPassword: '¿Olvidaste tu contraseña?',
        signIn: 'Iniciar Sesión',
        createAccount: 'Crear Cuenta',
        registerAccount: 'Registrar Cuenta',
        signInWithTFD: 'Iniciar sesión con The Flying Dutchmen',
        noAccount: '¿No tienes una cuenta?',
        haveAccount: '¿Ya tienes una cuenta?',
        createOne: 'Crear una',
        emailHelp: 'Utilizado para inicio de sesión de admin y notificaciones.'
      },
      common: {
        loading: 'Cargando...',
        error: 'Error',
        success: 'Éxito',
        save: 'Guardar',
        saving: 'Guardando...',
        cancel: 'Cancelar',
        delete: 'Eliminar',
        edit: 'Editar',
        back: 'Volver',
        close: 'Cerrar',
        actions: 'Acciones',
        details: 'Detalles',
        date: 'Fecha',
        score: 'Puntuación',
        winner: 'Ganador',
        rank: 'Posición',
        view: 'Ver',
        viewEdit: 'Ver/Editar',
        filter: 'Filtrar',
        search: 'Buscar',
        confirm: 'Confirmar',
        yes: 'Sí',
        no: 'No',
        all: 'Todos',
        none: 'Ninguno',
        apply: 'Aplicar',
        reset: 'Restablecer',
        selectAll: 'Seleccionar Todos',
        uncheckAll: 'Desmarcar Todos',
        copy: 'Copiar',
        or: 'o',
        bulkActions: 'Acciones en Bloque',
        allStatus: 'Todos los Estados',
        saveChanges: 'Guardar Cambios',
        plays: 'Partidas',
        players: 'Jugadores',
        wins: 'victorias',
        winsCap: 'Victorias',
        results: 'Resultados',
        active: 'Activo',
        inactive: 'Inactivo',
        viewEdit: 'Ver/Editar',
        manage: 'Administrar',
        managing: 'Administrando'
      },
      account: {
        yourClubs: 'Tus Clubes',
        noClubsYet: 'Aún no tienes ningún club. Haz clic en "Añadir un Club" arriba para crear tu primer club.',
        createNewClub: 'Crear Nuevo Club',
        themeFor: 'Tema para',
        activeTheme: 'Tema Activo',
        clickToApply: 'Clic para aplicar',
        accountInfo: 'Información de la Cuenta',
        adminDetails: '👤 Detalles del Administrador',
        accountId: 'ID de Cuenta',
        accountType: 'Tipo de Cuenta',
        clubQuota: 'Cupo de Clubes',
        memberSince: 'Miembro Desde',
        portfolioStats: '📊 Estadísticas Generales',
        totalMembers: 'Total de Miembros',
        totalGames: 'Total de Juegos',
        totalPlaysLogged: 'Total de Partidas',
        championsCrowned: 'Campeones Coronados',
        teamsRegistered: 'Equipos Registrados',
        activeContext: '🎯 Contexto Activo',
        activeClub: 'Club Activo',
        clubId: 'ID del Club',
        clubOwner: 'Propietario del Club',
        administrators: 'Administradores',
        default: 'Predeterminado',
        total: 'Total',
        managing: 'Administrando',
        manage: 'Administrar'
      },
      gametype: {
        winner_losers: 'Ganador / Perdedores',
        ranked: 'Clasificación (1º, 2º, etc.)',
        teams: 'Equipos',
        team: 'Equipos',
        cooperative: 'Cooperativo',
        individual: 'Individual'
      },
      confirm: {
        deleteTitle: 'Confirmar Eliminación',
        deleteMessage: '¿Estás seguro de que deseas eliminar este elemento? Esta acción no se puede deshacer.',
        deleteButton: 'Eliminar',
        cancelButton: 'Cancelar'
      },
      empty: {
        noResults: 'Aún no se han registrado partidas.',
        noMembers: 'Aún no hay miembros registrados.',
        noTeams: 'Aún no hay equipos creados.',
        noGames: 'Aún no hay juegos en el catálogo.',
        noGamesDesc: 'Añade algunos juegos a tu club antes de registrar resultados.',
        noChampions: 'Aún no hay campeones coronados.'
      },
      validation: {
        requiredField: 'Este campo es obligatorio.',
        invalidEmail: 'Por favor ingresa un correo electrónico válido.',
        invalidNumber: 'Por favor ingresa un número válido.'
      },
      members: {
        name: 'Nombre',
        nickname: 'Apodo',
        totalWins: 'Victorias Totales',
        trophies: 'Trofeos',
        status: 'Estado',
        allStatus: 'Todos los Estados',
        active: 'Activo',
        inactive: 'Inactivo',
        searchPlaceholder: 'Buscar miembros...'
      },
      teams: {
        teamName: 'Nombre del Equipo',
        created: 'Creado',
        selectTeamMembers: 'Seleccionar Miembros del Equipo:',
        searchPlaceholder: 'Buscar equipos...'
      },
      champions: {
        seasonTitle: 'Temporada / Título',
        awarded: 'Fecha Otorgada',
        currentChampion: '👑 Campeón Actual',
        formerChampion: 'Ex Campeón',
        searchPlaceholder: 'Buscar campeones...'
      },
      games: {
        image: 'Imagen',
        gameName: 'Nombre del Juego',
        players: 'Jugadores',
        minPlayers: 'Mín. Jugadores',
        maxPlayers: 'Máx. Jugadores',
        playCount: 'Partidas Jugadas',
        searchPlaceholder: 'Buscar juegos...'
      },
      results: {
        game: 'Juego',
        winner: 'Ganador / Resultado',
        duration: 'Duración',
        selectWinner: 'Seleccionar Ganador',
        selectSecondPlace: 'Seleccionar Segundo Lugar',
        selectPlace: 'Seleccionar {{place}}',
        secondPlace: 'Segundo Lugar',
        addPlace: 'Añadir Lugar',
        selectWinningTeam: 'Seleccionar Equipo Ganador',
        selectLosers: 'Seleccionar Perdedores:',
        outcome: 'Resultado:',
        victory: 'Victoria',
        defeat: 'Derrota',
        selectPlayers: 'Seleccionar Jugadores:',
        winningTeam: 'Equipo Ganador:',
        selectLosingTeams: 'Seleccionar Equipo(s) Perdedor(es):',
        notes: 'Notas:',
        backToResults: 'Volver a Resultados',
        deleteResult: 'Eliminar Resultado',
        searchPlaceholder: 'Buscar resultados...',
        hours: 'hrs',
        minutes: 'min',
        datePlayed: 'Fecha de Partida'
      },
      analytics: {
        title: 'Estadísticas y Tendencias',
        memberWinsTitle: 'Victorias de Miembros y sus Equipos',
        individualWins: 'Victorias Individuales',
        teamWins: 'Victorias en Equipo',
        mostPlayedGames: 'Juegos Más Jugados (Todos los Juegos)',
        ignored: 'Ignorados:',
        resetAll: 'Restablecer Todo',
        clickBarHint: 'Haz clic en cualquier barra para ignorar un juego',
        clickBarTooltip: '(Haz clic en la barra para ignorar el juego)',
        winRatesByGameType: 'Tasas de Victoria por Tipo de Juego',
        winnerLosersGames: 'Juegos de Ganador/Perdedor',
        rankedGames: 'Juegos Clasificatorios (1er Lugar)',
        teamGames: 'Juegos en Equipo',
        coopGames: 'Juegos Cooperativos'
      },
      headers: {
        accountSettings: 'Configuración de la Cuenta',
        editClub: 'Editar Club',
        viewClub: 'Ver Club',
        manageClubLogo: 'Administrar Logo del Club',
        manageTrophyImage: 'Administrar Imagen de Trofeo',
        selectClub: 'Seleccionar Club',
        optimizeCoverImages: 'Optimizar Imágenes de Portada',
        gameDays: 'Días de Juego',
        gameDetails: 'Detalles del Juego',
        playDetails: 'Detalles de la Partida',
        teamPlayDetails: 'Detalles de Juego en Equipo',
        cooperativePlayDetails: 'Detalles de Juego Cooperativo',
        viewEditMember: 'Ver/Editar Miembro',
        viewEditChampion: 'Ver/Editar Campeón',
        editTeam: 'Editar Equipo',
        manageMembers: 'Administrar Miembros de {{club}} ({{count}})',
        manageTeams: 'Administrar Equipos de {{club}} ({{count}})',
        manageGames: 'Administrar Juegos de {{club}} ({{count}})',
        manageChampions: 'Administrar Campeones de {{club}} ({{count}})',
        manageResults: 'Administrar Resultados de {{club}} ({{count}})',
        addGameResult: 'Añadir Resultado de Juego ({{club}})',
        editGameResult: 'Editar Resultado de Juego ({{club}})',
        gameResultsDate: 'Resultados de Juegos - {{date}}',
        gamePlayDetails: '{{game}} - Detalles de la Partida',
        gameTeamPlayDetails: '{{game}} - Detalles de Juego en Equipo',
        gameCoopPlayDetails: '{{game}} - Juego Cooperativo',
        viewAndEditGame: 'Ver y Editar {{game}}'
      }
    },
    ja: {
      nav: {
        home: 'ホーム',
        members: 'メンバー',
        teams: 'チーム',
        champions: 'チャンピオン',
        games: 'ゲーム',
        results: '対戦結果',
        findClub: 'クラブを探す',
        account: 'アカウント',
        login: 'ログイン',
        logout: 'ログアウト',
        register: 'クラブ登録',
        admin: '管理',
        viewPublic: '公開ページを見る',
        switchClub: 'クラブ切替',
        settings: '設定'
      },
      landing: {
        heroTitle1: 'すべての勝利を記録し、',
        heroTitle2: 'すべてのチャンピオンを称えよう。',
        heroSubtitle: 'ボードゲームクラブのメンバー管理、戦績記録、王座決定のためのオールインワン・プラットフォーム。',
        freeToUse: '完全無料',
        unlimited: 'ゲーム＆メンバー無制限',
        registerClub: 'クラブを登録する',
        findYourClub: 'クラブを探す',
        explorePublicClubs: '公開クラブ一覧',
        searchPlaceholder: 'クラブ名や都市名で検索...',
        noClubsFound: '一致するクラブが見つかりませんでした。',
        viewClub: 'クラブを見る',
        featuresHeading: 'ボードゲームファンのために構築',
        featMembersTitle: 'メンバー管理',
        featMembersDesc: '名簿管理、プレイ回数記録、ゲームごとの個別戦績を追跡。',
        featResultsTitle: '対戦ログ記録',
        featResultsDesc: '個人戦やチーム戦のスコア、順位、日付を詳細に記録。',
        featChampionsTitle: 'リーダーボード＆チャンピオン',
        featChampionsDesc: 'シーズン王者を決定し、カスタムトロフィーを授与して称賛。'
      },
      club: {
        members: 'メンバー',
        teams: 'チーム',
        champions: 'チャンピオン',
        games: 'ゲーム',
        results: '対戦結果',
        membersCount: '{{count}} 名のメンバー',
        teamsCount: '{{count}} チーム',
        gamesCount: '{{count}} 種のゲーム',
        resultsCount: '{{count}} 試合',
        championsCount: '{{count}} 名の王者',
        allGames: '全ゲーム',
        allMembers: '全メンバー',
        allTeams: '全チーム',
        recentResults: '最近の対戦結果',
        leaderboard: 'リーダーボード',
        stats: '統計情報',
        playedCount: 'プレイ回数: {{count}}回',
        wins: '勝利数',
        winRate: '勝率',
        totalPlays: '総プレイ数',
        viewDetails: '詳細を見る',
        matchDetails: '試合詳細',
        cumulativeWins: '期間別累計勝利数',
        last6Months: '過去6ヶ月',
        oneYear: '1年間',
        twoYears: '2年間',
        allTime: '全期間',
        lastMatchPlayed: '最近の対戦日',
        topChampion: '最多勝王者',
        playerCapacity: 'プレイ可能人数',
        recommendedPlayers: '推奨プレイ人数',
        totalRecordedMatches: '総対戦記録数',
        matchHistory: '対戦履歴'
      },
      admin: {
        dashboard: 'ダッシュボード',
        manageMembers: 'メンバー管理',
        manageTeams: 'チーム管理',
        manageGames: 'ゲーム管理',
        manageResults: '結果管理',
        manageChampions: 'チャンピオン管理',
        addResult: '結果を追加',
        editClub: 'クラブ編集',
        clubSettings: 'クラブ設定',
        addNewMember: 'メンバーを追加',
        saveMember: 'メンバーを保存',
        memberDetails: 'メンバー詳細',
        updateMember: 'メンバーを更新',
        deleteMember: 'メンバーを削除',
        fullName: '氏名',
        nickname: 'ニックネーム',
        email: 'メールアドレス',
        club: 'クラブ',
        status: 'ステータス',
        addNewTeam: 'チームを追加',
        saveTeam: 'チームを保存',
        teamDetails: 'チーム詳細',
        updateTeam: 'チームを更新',
        deleteTeam: 'チームを削除',
        addNewGame: 'ゲームを追加',
        saveGame: 'ゲームを保存',
        gameDetails: 'ゲーム詳細',
        updateGame: 'ゲームを更新',
        deleteGame: 'ゲームを削除',
        gameOverview: 'ゲーム概要＆統計',
        gameOverviewDesc: 'このゲームの主要統計とアクティビティサマリー。',
        currentImage: '現在の画像',
        removeCurrentImage: '現在の画像を削除',
        gameImage: 'ゲーム画像ファイル',
        uploadZoneText: 'クリックして選択、またはファイルをドラッグ＆ドロップ',
        uploadZoneHint: 'JPG, PNG, GIF (最大1MB)',
        orImageUrl: 'または画像のURLリンク',
        imageUrlHint: '画像ファイルへの直接リンクURLを貼り付け',
        matchType: 'ゲーム形式',
        winnerLosers: '勝者 / 敗者',
        ranked: '順位制 (1位, 2位, 3位...)',
        teams: 'チーム戦',
        cooperative: '協力型',
        addNewResult: '新規結果追加',
        crownChampion: 'チャンピオンを追加',
        saveChampion: 'チャンピオンを保存',
        championDetails: 'チャンピオン詳細',
        updateChampion: 'チャンピオンを更新',
        deleteChampion: 'チャンピオンを削除',
        selectMember: 'メンバーを選択',
        clubName: 'クラブ名:',
        clubNameLabel: 'クラブ名',
        clubLogo: 'クラブロゴ:',
        removeCurrentLogo: '現在のロゴを削除',
        uploadLogoText: 'クリックして選択、またはロゴをドラッグ＆ドロップ',
        uploadLogoHint: 'JPG, PNG, GIF (最大1MB)',
        clubSlug: 'クラブURLスラッグ (任意):',
        clubSlugDesc: '設定すると domain.com/slug でクラブに直接アクセスできます',
        vanityUrl: '現在のカスタムURL:',
        jsonUrl: 'JSON API URL:',
        jsonUrlDesc: 'このリンクからクラブ戦績のJSONデータを取得できます。',
        deleteClub: 'クラブを削除',
        addNewClub: 'クラブを追加',
        saveResult: '結果を保存',
        activateSelected: '選択項目を有効化',
        deactivateSelected: '選択項目を無効化',
        deleteSelected: '選択項目を削除'
      },
      auth: {
        username: 'ユーザー名またはメール',
        usernameOnly: 'ユーザー名',
        usernameHelp: '2〜50文字（半角英数字、アンダースコア）。',
        usernamePlaceholder: 'ユーザー名またはメールを入力',
        password: 'パスワード',
        passwordHelp: '8文字以上（英大文字・小文字・数字・記号を含む）。',
        passwordPlaceholder: 'パスワードを入力',
        confirmPassword: 'パスワードの再確認',
        rememberMe: 'ログイン状態を保持する',
        forgotPassword: 'パスワードをお忘れですか？',
        signIn: 'ログイン',
        createAccount: 'アカウント作成',
        registerAccount: 'アカウントを登録',
        signInWithTFD: 'The Flying Dutchmen でログイン',
        noAccount: 'アカウントをお持ちでないですか？',
        haveAccount: 'すでにアカウントをお持ちですか？',
        createOne: '新規作成',
        emailHelp: '管理者ログインおよび通知に使用されます。'
      },
      common: {
        loading: '読み込み中...',
        error: 'エラー',
        success: '成功',
        save: '保存',
        saving: '保存中...',
        cancel: 'キャンセル',
        delete: '削除',
        edit: '編集',
        back: '戻る',
        close: '閉じる',
        actions: '操作',
        details: '詳細',
        date: '日付',
        score: 'スコア',
        winner: '勝者',
        rank: '順位',
        view: '表示',
        viewEdit: '表示/編集',
        filter: 'フィルター',
        search: '検索',
        confirm: '確認',
        yes: 'はい',
        no: 'いいえ',
        all: 'すべて',
        none: 'なし',
        apply: '適用',
        reset: 'リセット',
        selectAll: 'すべて選択',
        uncheckAll: 'すべて解除',
        copy: 'コピー',
        or: 'または',
        bulkActions: '一括操作',
        allStatus: 'すべてのステータス',
        saveChanges: '変更を保存',
        plays: 'プレイ数',
        players: 'プレイヤー',
        wins: '勝',
        winsCap: '勝利数',
        results: '対戦結果',
        active: '有効',
        inactive: '無効',
        manage: '管理',
        managing: '管理中'
      },
      account: {
        yourClubs: '所属クラブ',
        noClubsYet: '所属しているクラブはまだありません。上の「クラブを追加」をクリックして最初のクラブを作成してください。',
        createNewClub: '新規クラブ作成',
        themeFor: 'テーマ設定:',
        activeTheme: '使用中のテーマ',
        clickToApply: 'クリックして適用',
        accountInfo: 'アカウント情報',
        adminDetails: '👤 管理者情報',
        accountId: 'アカウントID',
        accountType: 'アカウント種別',
        clubQuota: 'クラブ作成枠',
        memberSince: '登録日',
        portfolioStats: '📊 総合統計',
        totalMembers: '総メンバー数',
        totalGames: '総ゲーム数',
        totalPlaysLogged: '総対戦記録数',
        championsCrowned: '歴代チャンピオン数',
        teamsRegistered: '登録チーム数',
        activeContext: '🎯 現在の選択クラブ',
        activeClub: '現在のクラブ',
        clubId: 'クラブID',
        clubOwner: 'クラブ所有者',
        administrators: '管理者一覧',
        default: 'デフォルト',
        total: '合計',
        managing: '管理中',
        manage: '管理'
      },
      gametype: {
        winner_losers: '勝者・敗者',
        ranked: '順位制 (1位, 2位...)',
        teams: 'チーム戦',
        team: 'チーム戦',
        cooperative: '協力プレイ',
        individual: '個人戦'
      },
      confirm: {
        deleteTitle: '削除の確認',
        deleteMessage: '本当にこの項目を削除しますか？この操作は取り消せません。',
        deleteButton: '削除',
        cancelButton: 'キャンセル'
      },
      empty: {
        noResults: '記録された試合はまだありません。',
        noMembers: '登録されたメンバーはまだいません。',
        noTeams: '作成されたチームはまだありません。',
        noGames: 'ゲームはまだ登録されていません。',
        noGamesDesc: '結果を記録する前に、まずクラブにゲームを登録してください。',
        noChampions: 'チャンピオンはまだいません。'
      },
      validation: {
        requiredField: 'この項目は必須です。',
        invalidEmail: '有効なメールアドレスを入力してください。',
        invalidNumber: '有効な数値を入力してください。'
      },
      members: {
        name: '氏名',
        nickname: 'ニックネーム',
        totalWins: '総勝利数',
        trophies: 'トロフィー',
        status: 'ステータス',
        allStatus: 'すべてのステータス',
        active: '有効',
        inactive: '無効',
        searchPlaceholder: 'メンバーを検索...'
      },
      teams: {
        teamName: 'チーム名',
        created: '作成日',
        selectTeamMembers: 'チームメンバーを選択:',
        searchPlaceholder: 'チームを検索...'
      },
      champions: {
        seasonTitle: 'シーズン / タイトル',
        awarded: '授与日',
        currentChampion: '👑 現在のチャンピオン',
        formerChampion: '元チャンピオン',
        searchPlaceholder: 'チャンピオンを検索...'
      },
      games: {
        image: '画像',
        gameName: 'ゲーム名',
        players: 'プレイヤー',
        minPlayers: '最小人数',
        maxPlayers: '最大人数',
        playCount: 'プレイ回数',
        searchPlaceholder: 'ゲームを検索...'
      },
      results: {
        game: 'ゲーム',
        winner: '勝者 / 結果',
        duration: '所要時間',
        selectWinner: '勝者を選択',
        selectSecondPlace: '2位を選択',
        selectPlace: '{{place}}を選択',
        secondPlace: '2位',
        addPlace: '順位を追加',
        selectWinningTeam: '勝利チームを選択',
        selectLosers: '敗者を選択:',
        outcome: '勝敗結果:',
        victory: '勝利',
        defeat: '敗北',
        selectPlayers: 'プレイヤーを選択:',
        winningTeam: '勝利チーム:',
        selectLosingTeams: '敗北チームを選択:',
        notes: 'メモ:',
        backToResults: '結果一覧に戻る',
        deleteResult: '結果を削除',
        searchPlaceholder: '対戦結果を検索...',
        hours: '時間',
        minutes: '分',
        datePlayed: '対戦日時'
      },
      analytics: {
        title: '分析とトレンド',
        memberWinsTitle: 'メンバーおよび所属チームの勝利数',
        individualWins: '個人勝利',
        teamWins: 'チーム勝利',
        mostPlayedGames: '最もプレイされたゲーム (全ゲーム)',
        ignored: '除外中:',
        resetAll: 'すべてリセット',
        clickBarHint: 'バーをクリックしてゲームを除外',
        clickBarTooltip: '(バーをクリックして除外)',
        winRatesByGameType: 'ゲーム形式ごとの勝率',
        winnerLosersGames: '勝者・敗者ゲーム',
        rankedGames: '順位制ゲーム (1位)',
        teamGames: 'チーム戦ゲーム',
        coopGames: '協力ゲーム'
      },
      headers: {
        accountSettings: 'アカウント設定',
        editClub: 'クラブ設定',
        viewClub: 'クラブ詳細',
        manageClubLogo: 'クラブロゴ管理',
        manageTrophyImage: 'トロフィー画像管理',
        selectClub: 'クラブを選択',
        optimizeCoverImages: 'カバー画像の最適化',
        gameDays: 'ゲームデイ一覧',
        gameDetails: 'ゲーム詳細',
        playDetails: 'プレイ詳細',
        teamPlayDetails: 'チームプレイ詳細',
        cooperativePlayDetails: '協力プレイ詳細',
        viewEditMember: 'メンバー詳細・編集',
        viewEditChampion: 'チャンピオン詳細・編集',
        editTeam: 'チーム編集',
        manageMembers: '{{club}} メンバー管理 ({{count}}人)',
        manageTeams: '{{club}} チーム管理 ({{count}}チーム)',
        manageGames: '{{club}} ゲーム管理 ({{count}}本)',
        manageChampions: '{{club}} 歴代チャンピオン管理 ({{count}}名)',
        manageResults: '{{club}} 対戦結果管理 ({{count}}件)',
        addGameResult: '対戦結果の追加 ({{club}})',
        editGameResult: '対戦結果の編集 ({{club}})',
        gameResultsDate: '対戦結果 - {{date}}',
        gamePlayDetails: '{{game}} - プレイ詳細',
        gameTeamPlayDetails: '{{game}} - チームプレイ詳細',
        gameCoopPlayDetails: '{{game}} - 協力プレイ',
        viewAndEditGame: '{{game}} 詳細・編集'
      }
    }
  };

  const SUPPORTED_LANGS = ['en', 'es', 'ja'];
  let currentLang = 'en';

  function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length >= 2) {
      for (let i = parts.length - 1; i >= 1; i--) {
        const val = decodeURIComponent(parts[i].split(';').shift().trim());
        if (SUPPORTED_LANGS.includes(val)) return val;
      }
      return decodeURIComponent(parts.pop().split(';').shift().trim());
    }
    return null;
  }

  function getCookieDomain() {
    const hostname = window.location.hostname;
    if (!hostname || hostname === 'localhost' || /^\d{1,3}(\.\d{1,3}){3}$/.test(hostname)) {
      return '';
    }
    if (hostname.endsWith('theflyingdutchmen.games')) {
      return '.theflyingdutchmen.games';
    }
    if (hostname.endsWith('theflyingdutchmen.com')) {
      return '.theflyingdutchmen.com';
    }
    const parts = hostname.split('.');
    if (parts.length >= 2) {
      return '.' + parts.slice(-2).join('.');
    }
    return '';
  }

  function setCookie(name, value, days = 365) {
    const d = new Date();
    d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
    const expires = `expires=${d.toUTCString()}`;
    const cookieDomain = getCookieDomain();
    const secureAttr = window.location.protocol === 'https:' ? '; Secure' : '';

    document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; SameSite=Lax${secureAttr}`;
    document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; SameSite=Lax`;

    if (cookieDomain) {
      document.cookie = `${name}=${encodeURIComponent(value)}; ${expires}; path=/; domain=${cookieDomain}; SameSite=Lax${secureAttr}`;
    } else {
      document.cookie = `${name}=${encodeURIComponent(value)}; ${expires}; path=/; SameSite=Lax${secureAttr}`;
    }
  }

  function getLanguage() {
    // 1. URL params
    try {
      const urlParams = new URLSearchParams(window.location.search);
      const urlLang = urlParams.get('lng') || urlParams.get('lang');
      if (urlLang && SUPPORTED_LANGS.includes(urlLang)) return urlLang;
    } catch (e) {}

    // 2. Cookie
    const cookieLang = getCookie('i18next');
    if (cookieLang && SUPPORTED_LANGS.includes(cookieLang)) return cookieLang;

    // 3. LocalStorage
    try {
      const stored = localStorage.getItem('site-language') || localStorage.getItem('fly-on-language');
      if (stored && SUPPORTED_LANGS.includes(stored)) return stored;
    } catch (e) {}

    // 4. Browser language
    const browserLang = (navigator.language || navigator.userLanguage || '').split('-')[0];
    if (SUPPORTED_LANGS.includes(browserLang)) return browserLang;

    return 'en';
  }

  function setLanguage(lang) {
    if (!SUPPORTED_LANGS.includes(lang)) return;
    currentLang = lang;
    try {
      localStorage.setItem('site-language', lang);
      localStorage.setItem('fly-on-language', lang);
    } catch (e) {}
    setCookie('i18next', lang);
    document.documentElement.lang = lang;
  }

  function t(key, params = {}) {
    const lang = getLanguage();
    const keys = key.split('.');
    let val = LOCALES[lang];

    for (const k of keys) {
      if (val && typeof val === 'object' && k in val) {
        val = val[k];
      } else {
        val = null;
        break;
      }
    }

    if (val === null || val === undefined) {
      val = LOCALES['en'];
      for (const k of keys) {
        if (val && typeof val === 'object' && k in val) {
          val = val[k];
        } else {
          return params.defaultValue || key;
        }
      }
    }

    if (typeof val === 'string') {
      return val.replace(/\{\{(\w+)\}\}/g, (match, pName) => {
        return params[pName] !== undefined ? params[pName] : match;
      });
    }

    return val || key;
  }

  function translateCompactHeaders(container = document) {
    container.querySelectorAll('h1.compact-header__title, h2.compact-header__title, [data-header-raw]').forEach(el => {
      if (!el.getAttribute('data-header-raw')) {
        el.setAttribute('data-header-raw', el.textContent.trim());
      }
      const raw = el.getAttribute('data-header-raw') || el.textContent.trim();

      // Check regex header patterns
      let m;
      if ((m = raw.match(/^Manage\s+(.+?)\s+Members\s*\((.+?)\)$/i))) {
        el.textContent = t('headers.manageMembers', { club: m[1], count: m[2] });
      } else if ((m = raw.match(/^Manage\s+(.+?)\s+Teams\s*\((.+?)\)$/i))) {
        el.textContent = t('headers.manageTeams', { club: m[1], count: m[2] });
      } else if ((m = raw.match(/^Manage\s+(.+?)\s+Games\s*\((.+?)\)$/i))) {
        el.textContent = t('headers.manageGames', { club: m[1], count: m[2] });
      } else if ((m = raw.match(/^Manage\s+(.+?)\s+Champions\s*\((.+?)\)$/i))) {
        el.textContent = t('headers.manageChampions', { club: m[1], count: m[2] });
      } else if ((m = raw.match(/^Manage\s+(.+?)\s+Results\s*\((.+?)\)$/i))) {
        el.textContent = t('headers.manageResults', { club: m[1], count: m[2] });
      } else if ((m = raw.match(/^Add\s+Game\s+Result\s*\((.+?)\)$/i))) {
        el.textContent = t('headers.addGameResult', { club: m[1] });
      } else if ((m = raw.match(/^Edit\s+Game\s+Result\s*\((.+?)\)$/i))) {
        el.textContent = t('headers.editGameResult', { club: m[1] });
      } else if ((m = raw.match(/^Game\s+Results\s*-\s*(.+)$/i))) {
        el.textContent = t('headers.gameResultsDate', { date: m[1] });
      } else if ((m = raw.match(/^(.+?)\s*-\s*Play\s+Details$/i))) {
        el.textContent = t('headers.gamePlayDetails', { game: m[1] });
      } else if ((m = raw.match(/^(.+?)\s*-\s*Team\s+Play\s+Details$/i))) {
        el.textContent = t('headers.gameTeamPlayDetails', { game: m[1] });
      } else if ((m = raw.match(/^(.+?)\s*-\s*Cooperative\s+Play$/i))) {
        el.textContent = t('headers.gameCoopPlayDetails', { game: m[1] });
      } else if ((m = raw.match(/^View\s+and\s+Edit\s+(.+)$/i))) {
        el.textContent = t('headers.viewAndEditGame', { game: m[1] });
      } else if (raw === 'Account Settings') {
        el.textContent = t('headers.accountSettings');
      } else if (raw === 'Edit Club') {
        el.textContent = t('headers.editClub');
      } else if (raw === 'View Club') {
        el.textContent = t('headers.viewClub');
      } else if (raw === 'Manage Club Logo') {
        el.textContent = t('headers.manageClubLogo');
      } else if (raw === 'Manage Trophy Image') {
        el.textContent = t('headers.manageTrophyImage');
      } else if (raw === 'Select Club') {
        el.textContent = t('headers.selectClub');
      } else if (raw === 'Optimize Cover Images') {
        el.textContent = t('headers.optimizeCoverImages');
      } else if (raw === 'Game Days') {
        el.textContent = t('headers.gameDays');
      } else if (raw === 'Game Details') {
        el.textContent = t('headers.gameDetails');
      } else if (raw === 'Play Details') {
        el.textContent = t('headers.playDetails');
      } else if (raw === 'Team Play Details') {
        el.textContent = t('headers.teamPlayDetails');
      } else if (raw === 'Cooperative Play Details') {
        el.textContent = t('headers.cooperativePlayDetails');
      } else if (raw === 'View/Edit Member') {
        el.textContent = t('headers.viewEditMember');
      } else if (raw === 'View/Edit Champion') {
        el.textContent = t('headers.viewEditChampion');
      } else if (raw === 'Edit Team') {
        el.textContent = t('headers.editTeam');
      }
    });
  }

  function translateSelectOptions(container = document) {
    const optionMap = {
      'Select Winner': 'results.selectWinner',
      'Select Second Place': 'results.selectSecondPlace',
      'Select Winning Team': 'results.selectWinningTeam',
      'Select Member': 'admin.selectMember',
      'All Status': 'members.allStatus',
      'Active': 'members.active',
      'Inactive': 'members.inactive',
      'All Games': 'club.allGames',
      'Bulk Actions': 'common.bulkActions',
      'Activate Selected': 'admin.activateSelected',
      'Deactivate Selected': 'admin.deactivateSelected',
      'Delete Selected': 'admin.deleteSelected',
      'Winner / Losers': 'gametype.winner_losers',
      'Ranked (1st, 2nd, etc.)': 'gametype.ranked',
      'Teams': 'gametype.teams',
      'Cooperative': 'gametype.cooperative'
    };

    container.querySelectorAll('select option').forEach(opt => {
      if (!opt.getAttribute('data-raw-text')) {
        opt.setAttribute('data-raw-text', opt.textContent.trim());
      }
      const raw = opt.getAttribute('data-raw-text');

      if (opt.hasAttribute('data-i18n')) {
        opt.textContent = t(opt.getAttribute('data-i18n'));
        return;
      }

      if (optionMap[raw]) {
        opt.textContent = t(optionMap[raw]);
      } else if (raw.startsWith('Select ') && raw.endsWith(' Place')) {
        opt.textContent = t('results.selectPlace', { place: raw.replace('Select ', '') });
      }
    });
  }

  function translateDOM(container = document) {
    // 1. Text elements
    container.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.getAttribute('data-i18n');
      if (key) {
        let params = {};
        const paramsAttr = el.getAttribute('data-i18n-params');
        if (paramsAttr) {
          try { params = JSON.parse(paramsAttr); } catch (e) {}
        }
        el.textContent = t(key, params);
      }
    });

    // 2. HTML elements
    container.querySelectorAll('[data-i18n-html]').forEach(el => {
      const key = el.getAttribute('data-i18n-html');
      if (key) {
        let params = {};
        const paramsAttr = el.getAttribute('data-i18n-params');
        if (paramsAttr) {
          try { params = JSON.parse(paramsAttr); } catch (e) {}
        }
        el.innerHTML = t(key, params);
      }
    });

    // 3. Placeholders
    container.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
      const key = el.getAttribute('data-i18n-placeholder');
      if (key) {
        el.setAttribute('placeholder', t(key));
      }
    });

    // 4. Titles
    container.querySelectorAll('[data-i18n-title]').forEach(el => {
      const key = el.getAttribute('data-i18n-title');
      if (key) {
        el.setAttribute('title', t(key));
      }
    });

    // 5. Aria-labels
    container.querySelectorAll('[data-i18n-aria-label]').forEach(el => {
      const key = el.getAttribute('data-i18n-aria-label');
      if (key) {
        el.setAttribute('aria-label', t(key));
      }
    });

    // 6. Form values
    container.querySelectorAll('[data-i18n-value]').forEach(el => {
      const key = el.getAttribute('data-i18n-value');
      if (key) {
        el.setAttribute('value', t(key));
      }
    });

    // 7. Compact Headers auto-translation
    translateCompactHeaders(container);

    // 8. Select menu placeholder options auto-translation
    translateSelectOptions(container);
  }

  // Initialize language on load
  currentLang = getLanguage();
  document.documentElement.lang = currentLang;

  // React to navbar events
  function handleLanguageChange(lang) {
    if (lang && SUPPORTED_LANGS.includes(lang)) {
      setLanguage(lang);
      translateDOM();
    }
  }

  window.addEventListener('tfd-language-change', (e) => {
    const lang = e.detail && e.detail.language;
    if (lang) handleLanguageChange(lang);
  });

  window.addEventListener('languageChanged', (e) => {
    const lang = e.detail && e.detail.language;
    if (lang) handleLanguageChange(lang);
  });

  // Auto-translate on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => translateDOM());
  } else {
    translateDOM();
  }

  // Global exports
  const i18nEngine = {
    getLanguage,
    setLanguage,
    t,
    translateDOM,
    LOCALES
  };

  window.tfdI18n = i18nEngine;
  window.statsI18n = i18nEngine;
  window.t = t;

})(typeof window !== 'undefined' ? window : this);
