INSERT INTO users (line_user_id, name, role) 
VALUES ('U2d6b2ccc73c20effa2310a93a00a9b14', '行政倉管', 'ADMIN_OFFICE') 
ON DUPLICATE KEY UPDATE role='ADMIN_OFFICE', name='行政倉管';
