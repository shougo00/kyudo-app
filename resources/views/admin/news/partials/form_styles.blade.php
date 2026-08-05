<style>
.news-form {
    display: grid;
    gap: 14px;
}

.news-form-panel {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #fff;
    padding: 16px;
}

.recipient-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 8px;
}

.recipient-option {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 54px;
    padding: 10px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
}

.recipient-option input {
    flex: 0 0 auto;
}

.recipient-option span {
    display: grid;
    min-width: 0;
}

.recipient-option strong,
.recipient-option small {
    overflow-wrap: anywhere;
}

.recipient-option small {
    color: #6c757d;
}

.current-news-image img {
    width: min(100%, 420px);
    max-height: 260px;
    object-fit: cover;
    border: 1px solid #dee2e6;
    border-radius: 8px;
}
</style>
