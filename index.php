<?php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Your Wedding | Gallery & Client Access</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-MkM9+dU5CPtz+VRrx7tIw6V0Tp9SHFExi+b0dYV16zJZyrUxjlX+8llc8frlJYe1jKhh598MBXEDqUS1bJXgBA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <section class="hero is-medium is-bold">
            <div class="hero-body">
                <div class="container">
                    <p class="subtitle">LochStudios Presents</p>
                    <h1 class="title">Your Wedding</h1>
                    <p class="subtitle is-4">Celebrate the photos LochStudios captured for you with a private gallery for every couple.</p>
                    <div class="buttons mt-5">
                        <a class="button is-primary is-medium" href="/login.php">
                            <span class="icon"><i class="fas fa-user-lock"></i></span>
                            <span>Admins Only</span>
                        </a>
                        <a class="button is-light is-medium" href="#contact">
                            <span class="icon"><i class="fas fa-envelope"></i></span>
                            <span>Contact Us</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
        <section class="section">
            <div class="container">
                <div class="columns is-vcentered">
                    <div class="column">
                        <h2 class="title">Who we are</h2>
                        <p>
                            We're LochStudios. Your Wedding is the private gallery site we build for our couples so they can
                            revisit the moments we captured on their wedding day in a secure, beautifully presented space.
                        </p>
                    </div>
                    <div class="column">
                        <figure class="image is-4by3">
                            <img src="https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80" alt="Wedding moment" />
                        </figure>
                    </div>
                </div>
            </div>
        </section>
        <section class="section has-background-white">
            <div class="container">
                <h2 class="title">What we do</h2>
                <div class="columns">
                    <div class="column has-text-dark">
                        <div class="box">
                            <span class="icon has-text-primary"><i class="fas fa-shield-halved fa-2x"></i></span>
                            <h3 class="title is-5">Private Galleries</h3>
                            <p>Every gallery is handcrafted by LochStudios with password protection and thoughtful slugs.</p>
                        </div>
                    </div>
                    <div class="column">
                        <div class="box">
                            <span class="icon has-text-primary"><i class="fas fa-cloud-download-alt fa-2x"></i></span>
                            <h3 class="title is-5">Secure Hosting</h3>
                            <p>We keep the originals safe and deliver them to you through this responsive viewer.</p>
                        </div>
                    </div>
                    <div class="column">
                        <div class="box">
                            <span class="icon has-text-primary"><i class="fas fa-tachometer-alt fa-2x"></i></span>
                            <h3 class="title is-5">Fast Delivery</h3>
                            <p>We publish galleries shortly after editing so couples receive their photos while the memory is fresh.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class="section">
            <div class="container">
                <h2 class="title">Pricing</h2>
                <div class="columns">
                    <div class="column">
                        <div class="card pricing-card">
                            <header class="card-header">
                                <p class="card-header-title has-text-weight-bold">Starter</p>
                            </header>
                            <div class="card-content">
                                <p class="title is-3">$2,499</p>
                                <p>Full-day coverage gallery with a curated delivery experience.</p>
                                <ul>
                                    <li>LochStudios curated password delivery</li>
                                    <li>Gallery-friendly lightbox</li>
                                    <li>Responsive tech support</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="card pricing-card">
                            <header class="card-header">
                                <p class="card-header-title has-text-weight-bold">Premium</p>
                            </header>
                            <div class="card-content">
                                <p class="title is-3">$3,499</p>
                                <p>Multi-gallery package with expedited delivery and extra polish.</p>
                                <ul>
                                    <li>Tailored slugs, passwords & messaging</li>
                                    <li>Priority LochStudios support</li>
                                    <li>Monthly sharing summaries</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="card pricing-card">
                            <header class="card-header">
                                <p class="card-header-title has-text-weight-bold">Enterprise</p>
                            </header>
                            <div class="card-content">
                                <p class="title is-3">Custom</p>
                                <p>Custom partnerships for venues, teams, or large wedding programs.</p>
                                <ul>
                                    <li>Dedicated LochStudios gallery manager</li>
                                    <li>Automated gallery workflows</li>
                                    <li>Team-level sharing controls</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section id="contact" class="section has-background-light">
            <div class="container">
                <h2 class="title">Let's talk</h2>
                <div class="columns">
                    <div class="column">
                        <p class="subtitle">Reach out to LochStudios to set up your private gallery experience.</p>
                        <p><span class="has-text-weight-bold">Phone</strong></p>
                        <p><i class="fas fa-phone"></i> Australia: +61 (2) 5632-3092</p>
                        <p><i class="fas fa-phone"></i> New Zealand: +64 (9) 873-1233</p>
                        <p><i class="fas fa-phone"></i> United States: +1 (315) 879-6488</p>
                        <p><i class="fas fa-phone"></i> United Kingdom: +44 2080 899 548</p>
                        <p class="mt-3"><span class="has-text-weight-bold">Email</strong></p>
                        <p><i class="fas fa-envelope"></i> Sales: <a href="mailto:sales@lochstudios.com">sales@lochstudios.com</a></p>
                        <p><i class="fas fa-envelope"></i> Support: <a href="mailto:support@lochstudios.com">support@lochstudios.com</a></p>
                        <p><i class="fas fa-envelope"></i> Media: <a href="mailto:media@lochstudios.com">media@lochstudios.com</a></p>
                        <p class="mt-3"><span class="has-text-weight-bold">Locations</strong></p>
                        <p><i class="fas fa-mail-bulk"></i> Mailing Address: PO Box 219, South Grafton, NSW, 2460, Australia</p>
                        <p><i class="fas fa-building"></i> Office Location: Level 5, 115 Pitt Street, Sydney, NSW, 2000, Australia</p>
                    </div>
                    <div class="column">
                        <div class="box">
                            <form>
                                <div class="field">
                                    <label class="label">Studio Name</label>
                                    <div class="control">
                                        <input class="input" type="text" placeholder="Your Team" />
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="label">Email</label>
                                    <div class="control">
                                        <input class="input" type="email" placeholder="you@email.com" />
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="label">Message</label>
                                    <div class="control">
                                        <textarea class="textarea" placeholder="Tell us about your next wedding project"></textarea>
                                    </div>
                                </div>
                                <div class="field">
                                    <div class="control">
                                        <button class="button is-link">Send Request</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <footer class="footer">
            <div class="content has-text-centered">
                <p>&copy; <?php echo date('Y'); ?> Loch Studios · Your Wedding Gallery Experience</p>
                <p class="footer-links">
                    <a href="/login.php">Admin Portal</a>
                </p>
            </div>
        </footer>
    </body>
</html>