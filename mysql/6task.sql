SELECT SUM(b.price), SUM(a.amount), HOUR(a.time_created) as hour FROM `analytics` as a
JOIN boosterpack as b on a.object_id = b.id
WHERE a.time_created BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE()
GROUP BY hour, b.id;


SELECT u.wallet_total_refilled, SUM(a.amount), u.wallet_balance, u.likes_balance FROM user as u
JOIN analytics as a ON a.user_id = u.id
WHERE u.id = 1 AND a.action = 'buy_boosterpack';