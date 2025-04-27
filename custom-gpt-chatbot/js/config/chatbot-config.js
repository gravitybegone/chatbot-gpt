window.ChatbotConfig = {
    // Counties are fixed and accurate for North Florida
    counties: [
        { id: 'clay', name: 'Clay County' },
        { id: 'duval', name: 'Duval County' },
        { id: 'st. johns', name: 'St. Johns County' },
        { id: 'nassau', name: 'Nassau County' },
        { id: 'flagler', name: 'Flagler County' }
    ],
    
    // Cities organized by county
    cities: {
        'clay': [
            'orange park',
            'middleburg',
            'fleming island',
            'green cove springs',
            'penney farms',
            'keystone heights',
            'oakleaf',
            'doctors inlet',
            'lake asbury',
            'belmore'
        ],
        'duval': [
            'jacksonville',
            'jacksonville beach',
            'neptune beach',
            'atlantic beach',
            'baldwin',
            'mayport'
        ],
        'st. johns': [
            'st. augustine',
            'st. augustine beach',
            'ponte vedra',
            'ponte vedra beach',
            'nocatee',
            'fruit cove',
            'switzerland',
            'hastings',
            'world golf village',
            'palencia',
            'butler beach',
            'crescent beach'
        ],
        'nassau': [
            'fernandina beach',
            'yulee',
            'callahan',
            'hilliard',
            'bryceville',
            'amelia island'
        ],
        'flagler': [
            'palm coast',
            'bunnell',
            'flagler beach',
            'beverly beach',
            'marineland',
            'hammock'
        ]
    }
};