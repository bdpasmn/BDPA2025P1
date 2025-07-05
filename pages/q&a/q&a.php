<!DOCTYPE html>
<html lang="en" class="bg-gray-900 text-gray-300">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Q&A View Placeholder</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-300 font-sans max-w-4xl mx-auto p-6">

  <!-- Question Card -->
  <section class="bg-gray-800 rounded-lg shadow-lg p-6 mb-10">
    <div class="flex justify-between items-center mb-3">
      <div class="space-x-4 text-sm text-gray-400">
        <span>Asked: <time datetime="2025-07-01T14:23">Jul 1, 2025 14:23</time></span>
        <span>Views: 152</span>
        <span>Points: <span class="font-semibold text-gray-300">+12</span></span>
      </div>
      <div class="flex items-center space-x-3">
        <img src="https://letsenhance.io/static/73136da51c245e80edc6ccfe44888a99/1015f/MainBefore.jpg" alt="" class="w-10 h-10 rounded-full object-cover" />
        <div>
          <p class="font-semibold text-white">question_asker</p>
          <p class="text-sm text-gray-400">Level 4</p>
        </div>
      </div>
    </div>

    <h1 class="text-white text-3xl font-extrabold mb-5 tracking-tight">Example Question Title Placeholder</h1>

    <article class="prose prose-invert bg-gray-700 p-6 rounded-lg mb-8 max-w-none whitespace-pre-wrap leading-relaxed text-gray-300">
    What is 123
    </article>

    <div class="flex space-x-4 mb-8">
      <button class="bg-blue-600 hover:bg-blue-700 text-blue-400 font-semibold px-5 py-3 rounded-lg shadow-md transition">
        ▲ Upvote
      </button>
      <button class="bg-blue-600 hover:bg-blue-700 text-blue-400 font-semibold px-5 py-3 rounded-lg shadow-md transition">
        ▼ Downvote
      </button>
    </div>

    <div>
      <h2 class="text-white font-semibold mb-4 text-lg">Comments</h2>
      <ul class="space-y-3 text-gray-400 text-sm">
        <li><span>This is a comment on the question.</span> — <em class="text-gray-400">commenter1</em></li>
        <li><span>Another comment here.</span> — <em class="text-gray-400">commenter2</em></li>
      </ul>
    </div>

    <form class="mt-6 space-y-3" onsubmit="event.preventDefault()">
      <label for="new-comment" class="block text-white font-medium">Add a new comment:</label>
      <input
        type="text"
        id="new-comment"
        maxlength="150"
        placeholder="Write a comment..."
        class="w-full bg-gray-700 border border-gray-600 rounded-md px-4 py-3 placeholder-gray-400 text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 transition"
      />
      <button
        type="submit"
        class="bg-blue-600 hover:bg-blue-700 text-blue-400 font-semibold px-6 py-3 rounded-lg shadow-md transition"
      >Add Comment</button>
    </form>
  </section>

  <!-- Answers Section -->
  <section>
    <h2 class="text-white text-3xl font-extrabold mb-8 tracking-tight">Answers</h2>

    <!-- Accepted Answer -->
    <article class="bg-gray-800 border border-blue-600 rounded-lg p-6 mb-8 relative shadow-lg">
      <span
        class="absolute top-3 right-3 bg-blue-600 text-blue-300 font-semibold px-3 py-1 rounded-full text-sm select-none shadow"
        >Accepted Answer</span
      >
      <br />
      <div class="flex justify-between items-center mb-5">
        <div class="text-gray-300 font-semibold text-lg">Points: +25</div>
        <div class="flex items-center space-x-4">
          <img src="https://letsenhance.io/static/73136da51c245e80edc6ccfe44888a99/1015f/MainBefore.jpg"  class="w-10 h-10 rounded-full object-cover shadow" />
          <div>
            <p class="font-semibold text-white text-lg">answer_user1</p>
            <p class="text-sm text-gray-400">Level 5</p>
          </div>
        </div>
        <time class="text-gray-400 text-sm" datetime="2025-07-02T09:15">Answered: Jul 2, 2025 09:15</time>
      </div>

      <article class="prose prose-invert bg-gray-700 p-6 rounded-lg mb-6 max-w-none whitespace-pre-wrap leading-relaxed text-gray-300 shadow-inner">
The answer is 123
    </article>

      <div class="flex space-x-4 mb-6">
        <button class="bg-blue-600 hover:bg-blue-700 text-blue-400 font-semibold px-5 py-3 rounded-lg shadow-md transition">
          ▲ Upvote
        </button>
        <button class="bg-blue-600 hover:bg-blue-700 text-blue-400 font-semibold px-5 py-3 rounded-lg shadow-md transition">
          ▼ Downvote
        </button>
      </div>

      <div>
        <h3 class="text-white font-semibold mb-3 text-lg">Comments</h3>
        <ul class="space-y-3 text-gray-400 text-sm mb-6">
          <li><span>Thanks for this answer!</span> — <em class="text-gray-400">commenter3</em></li>
        </ul>
      </div>

      <form class="space-y-3" onsubmit="event.preventDefault()">
        <label for="new-answer-comment-1" class="block text-white font-medium"
          >Add comment to this answer</label
        >
        <input
          type="text"
          id="new-answer-comment-1"
          maxlength="150"
          placeholder="Write a comment..."
          class="w-full bg-gray-700 border border-gray-600 rounded-md px-4 py-3 placeholder-gray-400 text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 transition"
        />
        <button
          type="submit"
          class="bg-blue-600 hover:bg-blue-700 text-blue-400 font-semibold px-6 py-3 rounded-lg shadow-md transition"
        >Add Comment</button>
      </form>
    </article>

    <!-- Other Answer -->
    <article class="bg-gray-800 border border-gray-600 rounded-lg p-6 mb-8 shadow-md">
      <div class="flex justify-between items-center mb-5">
        <div class="text-gray-300 font-semibold text-lg">Points: +8</div>
        <div class="flex items-center space-x-4">
          <img src="https://letsenhance.io/static/73136da51c245e80edc6ccfe44888a99/1015f/MainBefore.jpg"  class="w-10 h-10 rounded-full object-cover shadow" />
          <div>
            <p class="font-semibold text-white text-lg">answer_user2</p>
            <p class="text-sm text-gray-400">Level 3</p>
          </div>
        </div>
        <time class="text-gray-400 text-sm" datetime="2025-07-03T16:42">Answered: Jul 3, 2025 16:42</time>
      </div>

      <article class="prose prose-invert bg-gray-700 p-6 rounded-lg mb-6 max-w-none whitespace-pre-wrap leading-relaxed text-gray-300 shadow-inner">
        opiuw\WODJFIUGYEWIUHO
      </article>

      <div class="flex space-x-4 mb-6">
        <button class="bg-blue-600 hover:bg-blue-700 text-blue-400 font-semibold px-5 py-3 rounded-lg shadow-md transition">
          ▲ Upvote
        </button>
        <button class="bg-blue-600 hover:bg-blue-700 text-blue-400 font-semibold px-5 py-3 rounded-lg shadow-md transition">
          ▼ Downvote
        </button>
      </div>

      <div>
        <h3 class="text-white font-semibold mb-3 text-lg">Comments</h3>
        <ul class="space-y-3 text-gray-400 text-sm mb-6">
          <li><span>Could you clarify this point?</span> — <em class="text-gray-400">commenter4</em></li>
        </ul>
      </div>

      <form class="space-y-3" onsubmit="event.preventDefault()">
        <label for="new-answer-comment-2" class="block text-white font-medium"
          >Add comment to this answer:</label
        >
        <input
          type="text"
          id="new-answer-comment-2"
          maxlength="150"
          placeholder="Write a comment..."
          class="w-full bg-gray-700 border border-gray-600 rounded-md px-4 py-3 placeholder-gray-400 text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 transition"
        />
        <button
          type="submit"
          class="bg-blue-600 hover:bg-blue-700 text-blue-400 font-semibold px-6 py-3 rounded-lg shadow-md transition"
        >Add Comment</button>
      </form>
    </article>

    <!-- New Answer Form -->
    <section class="bg-gray-800 rounded-lg shadow-lg p-8">
      <h3 class="text-white text-2xl font-extrabold mb-6 tracking-tight">Add a new answer</h3>
      <form onsubmit="event.preventDefault()" class="space-y-6">
        <textarea
          id="new-answer"
          maxlength="3000"
          rows="7"
          placeholder="Write your answer here"
          class="w-full bg-gray-700 border border-gray-600 rounded-md px-5 py-4 placeholder-gray-400 text-gray-300 resize-none focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 transition shadow-inner"
        ></textarea>
        <button
          type="submit"
          class="bg-blue-600 hover:bg-blue-700 text-blue-400 font-semibold px-8 py-3 rounded-lg shadow-md transition"
        >Submit Answer</button>
      </form>
    </section>
  </section>
</body>
</html>
