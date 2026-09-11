<script>
    function onthiLeaderboardPage(config) {
        return {
            rows: config.rows,
            query: '',

            get normalized() { return this.query.trim().toLowerCase(); },

            get matchedRanks() {
                if (!this.normalized) return this.rows.map((r) => r.rank);
                return this.rows.filter((r) => r.search.includes(this.normalized)).map((r) => r.rank);
            },

            get visibleCount() { return this.matchedRanks.length; },

            matches(rank) { return this.matchedRanks.includes(rank); },
        };
    }
</script>
