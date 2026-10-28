

CREATE TABLE IF NOT EXISTS market_analysis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id INT NOT NULL UNIQUE,
    connectivity_score DECIMAL(5,2),
    infrastructure_score DECIMAL(5,2),
    demand_score DECIMAL(5,2),
    income_score DECIMAL(5,2),
    future_dev_score DECIMAL(5,2),
    competition_score DECIMAL(5,2),
    regulatory_score DECIMAL(5,2),
    total_score DECIMAL(5,2),
    raw_json_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB;
